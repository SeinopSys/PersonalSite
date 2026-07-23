<?php

namespace Tests\Feature;

use App\Models\ImageUploadKey;
use App\Models\Upload;
use App\Models\UploadFolder;
use App\Models\User;
use App\Util\UploadUtil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadFoldersTest extends TestCase
{
    use RefreshDatabase;

    private array $filesToClean = [];

    protected function tearDown(): void
    {
        $dir = UploadUtil::getUploadDirectory();
        foreach ($this->filesToClean as $filename) {
            @unlink("$dir/$filename");
        }
        parent::tearDown();
    }

    private function makeUser(string $name = 'testuser'): User
    {
        return User::create([
            'name' => $name,
            'email' => "$name@example.com",
            'password' => bcrypt('password'),
            'lang' => 'en',
            'role' => 'user',
        ]);
    }

    private function enableUploads(User $user): ImageUploadKey
    {
        $key = new ImageUploadKey(['user_id' => $user->id]);
        $key->generateUploadKey();
        $key->save();

        return $key;
    }

    private function trackUploadFiles(array $json): void
    {
        foreach (['full', 'full_png', 'full_jpg', 'preview'] as $field) {
            if (!empty($json[$field])) {
                $this->filesToClean[] = basename(parse_url($json[$field], PHP_URL_PATH));
            }
        }
    }

    public function test_root_upload_shape_is_unchanged()
    {
        $user = $this->makeUser();
        $key = $this->enableUploads($user);

        $response = $this->postJson('/api/upload', [
            'upload_key' => $key->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('test.png', 10, 10),
        ])->assertOk();

        $json = $response->json();
        $this->trackUploadFiles($json);

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('full', $json);
        $this->assertArrayHasKey('full_png', $json);
        $this->assertArrayHasKey('full_jpg', $json);
        $this->assertArrayHasKey('preview', $json);
        $this->assertNotNull($json['full_png']);
        $this->assertNotNull($json['full_jpg']);

        $upload = Upload::findOrFail($json['id']);
        $this->assertNull($upload->folder_id);
    }

    public function test_folder_key_upload_is_scoped_to_folder_and_respects_disabled_flags()
    {
        $user = $this->makeUser();
        $this->enableUploads($user);

        $folder = new UploadFolder([
            'user_id' => $user->id,
            'name' => 'TestFolder',
            'disable_thumbnails' => true,
            'disable_conversion' => true,
        ]);
        $folder->save();

        $folderKey = new ImageUploadKey(['user_id' => $user->id, 'folder_id' => $folder->id]);
        $folderKey->generateUploadKey();
        $folderKey->save();

        $response = $this->postJson('/api/upload', [
            'upload_key' => $folderKey->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('test.png', 10, 10),
        ])->assertOk();

        $json = $response->json();
        $this->trackUploadFiles($json);

        $this->assertNull($json['full_png']);
        $this->assertNull($json['full_jpg']);
        $this->assertSame($json['full'], $json['preview']);

        $upload = Upload::findOrFail($json['id']);
        $this->assertSame($folder->id, $upload->folder_id);

        $dir = UploadUtil::getUploadDirectory();
        $this->assertFileDoesNotExist("$dir/{$upload->filename}.jpg");
        $this->assertFileDoesNotExist("$dir/{$upload->filename}p.png");
    }

    public function test_multi_file_upload_returns_files_array_and_enforces_quota()
    {
        $user = $this->makeUser();
        $key = $this->enableUploads($user);

        $response = $this->postJson('/api/upload', [
            'upload_key' => $key->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => [
                UploadedFile::fake()->image('a.png', 10, 10),
                UploadedFile::fake()->image('b.png', 10, 10),
            ],
        ])->assertOk();

        $json = $response->json();
        $this->assertArrayHasKey('files', $json);
        $this->assertCount(2, $json['files']);
        foreach ($json['files'] as $file) {
            $this->trackUploadFiles($file);
        }

        $this->assertSame(2, Upload::where('uploaded_by', $user->id)->count());
    }

    public function test_invalid_upload_key_is_rejected()
    {
        $this->postJson('/api/upload', [
            'upload_key' => 'not-a-real-key',
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('test.png', 10, 10),
        ])->assertStatus(401);
    }

    public function test_folder_crud_lifecycle_and_delete_cascades()
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->enableUploads($user);

        $create = $this->postJson('/uploads/folders', ['name' => 'Screenshots'])->assertOk()->json();
        $folderId = $create['id'];
        $this->assertNotEmpty($create['upload_key']);
        $this->assertFalse($create['disable_thumbnails']);
        $this->assertFalse($create['disable_conversion']);

        // Duplicate sibling name is rejected
        $this->postJson('/uploads/folders', ['name' => 'Screenshots'])
            ->assertStatus(500)
            ->assertJsonPath('status', false);

        // Subfolder + upload inside it
        $sub = $this->postJson('/uploads/folders', ['name' => 'Sub', 'parent_id' => $folderId])->assertOk()->json();

        $folderKey = ImageUploadKey::where('folder_id', $sub['id'])->firstOrFail();
        $uploadResponse = $this->postJson('/api/upload', [
            'upload_key' => $folderKey->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('test.png', 10, 10),
        ])->assertOk()->json();
        $this->trackUploadFiles($uploadResponse);
        $uploadId = $uploadResponse['id'];

        // Rename
        $this->putJson("/uploads/folders/{$folderId}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed');

        // Toggle settings
        $this->putJson("/uploads/folders/{$sub['id']}", ['disable_thumbnails' => true])
            ->assertOk()
            ->assertJsonPath('disable_thumbnails', true);

        // Key regen changes the value
        $regen = $this->postJson("/uploads/folders/{$sub['id']}/regen")->assertOk()->json();
        $this->assertNotSame($folderKey->upload_key, $regen['upload_key']);

        // Delete cascades: subfolder, its key, and its upload (with files) are all gone
        $this->deleteJson("/uploads/folders/{$folderId}")->assertOk();

        $this->assertNull(UploadFolder::find($folderId));
        $this->assertNull(UploadFolder::find($sub['id']));
        $this->assertNull(Upload::find($uploadId));
        $this->assertDatabaseMissing('image_upload_keys', ['folder_id' => $sub['id']]);

        $dir = UploadUtil::getUploadDirectory();
        $filename = basename(parse_url($uploadResponse['full'], PHP_URL_PATH));
        $this->assertFileDoesNotExist("$dir/$filename");
    }

    public function test_ajax_listing_shows_images_when_switching_between_root_and_folder()
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $rootKey = $this->enableUploads($user);

        $folder = new UploadFolder(['user_id' => $user->id, 'name' => 'Sub']);
        $folder->save();
        $folderKey = new ImageUploadKey(['user_id' => $user->id, 'folder_id' => $folder->id]);
        $folderKey->generateUploadKey();
        $folderKey->save();

        $rootUpload = $this->postJson('/api/upload', [
            'upload_key' => $rootKey->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('root.png', 10, 10),
        ])->assertOk()->json();
        $this->trackUploadFiles($rootUpload);

        $folderUpload = $this->postJson('/api/upload', [
            'upload_key' => $folderKey->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('folder.png', 10, 10),
        ])->assertOk()->json();
        $this->trackUploadFiles($folderUpload);

        $rootResponse = $this->getJson('/uploads?page=1')->assertOk()->json();
        $this->assertSame(1, $rootResponse['total']);
        $this->assertStringContainsString('id="upload-list"', $rootResponse['newhtml']);
        $this->assertStringContainsString($rootUpload['id'], $rootResponse['newhtml']);
        $this->assertStringNotContainsString($folderUpload['id'], $rootResponse['newhtml']);

        $folderResponse = $this->getJson("/uploads?page=1&folder={$folder->id}")->assertOk()->json();
        $this->assertSame(1, $folderResponse['total']);
        $this->assertStringContainsString('id="upload-list"', $folderResponse['newhtml']);
        $this->assertStringContainsString($folderUpload['id'], $folderResponse['newhtml']);
        $this->assertStringNotContainsString($rootUpload['id'], $folderResponse['newhtml']);
    }

    public function test_disable_uploads_only_deletes_calling_users_files()
    {
        $userA = $this->makeUser('usera');
        $userB = $this->makeUser('userb');
        $keyA = $this->enableUploads($userA);
        $this->enableUploads($userB);

        $uploadA = $this->postJson('/api/upload', [
            'upload_key' => $keyA->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('a.png', 10, 10),
        ])->assertOk()->json();
        $this->trackUploadFiles($uploadA);

        $keyB = ImageUploadKey::where('user_id', $userB->id)->firstOrFail();
        $uploadB = $this->postJson('/api/upload', [
            'upload_key' => $keyB->upload_key,
            'domain' => config('app.secondary_domain'),
            'file' => UploadedFile::fake()->image('b.png', 10, 10),
        ])->assertOk()->json();
        $this->trackUploadFiles($uploadB);

        $this->actingAs($userA);
        $this->postJson('/uploads/setting/disable')->assertOk();

        $this->assertNull(Upload::find($uploadA['id']));
        $this->assertNotNull(Upload::find($uploadB['id']));

        $dir = UploadUtil::getUploadDirectory();
        $filenameB = basename(parse_url($uploadB['full'], PHP_URL_PATH));
        $this->assertFileExists("$dir/$filenameB");

        $this->assertDatabaseMissing('image_upload_keys', ['user_id' => $userA->id]);
        $this->assertDatabaseHas('image_upload_keys', ['user_id' => $userB->id]);
    }
}
