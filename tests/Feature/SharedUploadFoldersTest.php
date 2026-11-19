<?php

namespace Tests\Feature;

use App\Models\ImageUploadKey;
use App\Models\Upload;
use App\Models\UploadFolder;
use App\Models\User;
use App\Util\UploadUtil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedUploadFoldersTest extends TestCase
{
    use RefreshDatabase;

    private array $filesToClean = [];

    protected function tearDown(): void
    {
        foreach ($this->filesToClean as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function makeUser(string $name = 'owner'): User
    {
        $user = User::create(['name' => $name, 'email' => "$name@example.com", 'password' => bcrypt('password'), 'lang' => 'en', 'role' => 'user']);
        $key = new ImageUploadKey(['user_id' => $user->id]);
        $key->generateUploadKey();
        $key->save();

        return $user;
    }

    private function folder(User $user, string $name, ?UploadFolder $parent = null): UploadFolder
    {
        return UploadFolder::create(['user_id' => $user->id, 'name' => $name, 'parent_id' => $parent?->id]);
    }

    private function upload(User $user, ?UploadFolder $folder, string $name): Upload
    {
        $json = $this->actingAs($user)->postJson('/uploads/upload', [
            'file' => UploadedFile::fake()->image($name, 20, 20), 'folder' => $folder?->id,
        ])->assertJson(['status' => true])->json('upload');
        auth()->logout();
        $upload = Upload::findOrFail($json['id']);
        $dir = UploadUtil::getUploadDirectory();
        foreach (['full', 'jpeg', 'preview'] as $kind) {
            $this->filesToClean[] = "$dir/".$upload->getFilenames()[$kind];
        }

        return $upload;
    }

    private function share(User $user, UploadFolder $folder, array $data = []): UploadFolder
    {
        $this->actingAs($user)->postJson("/uploads/folders/{$folder->id}/share", $data)->assertJson(['status' => true]);
        auth()->logout();

        return $folder->fresh();
    }

    public function test_nothing_is_shared_until_a_link_is_generated(): void
    {
        $user = $this->makeUser();
        $folder = $this->folder($user, 'Trip');

        $this->assertFalse($folder->isShared());
        $this->assertNull($folder->shareUrl());
        $json = $this->actingAs($user)->getJson('/uploads/folders')->json('folders.0');
        $this->assertNull($json['share_url']);
    }

    public function test_generating_makes_a_long_random_encrypted_link_and_stopping_removes_it(): void
    {
        $user = $this->makeUser();
        $a = $this->share($user, $this->folder($user, 'A'));
        $b = $this->share($user, $this->folder($user, 'B'), ['expires_in_days' => 7]);

        $this->assertSame(UploadFolder::SHARE_TOKEN_LENGTH, strlen($a->share_token));
        $this->assertNotSame($a->share_token, $b->share_token);
        $this->assertNull($a->share_expires_at);
        $this->assertTrue($b->share_expires_at->isSameDay(now()->addDays(7)));
        $this->assertNotSame($a->share_token, \DB::table('upload_folders')->where('id', $a->id)->value('share_token'));

        $this->actingAs($user)->deleteJson("/uploads/folders/{$a->id}/share")->assertJson(['status' => true]);
        $this->assertFalse($a->fresh()->isShared());
        $this->assertNull(\DB::table('upload_folders')->where('id', $a->id)->value('share_token_index'));
    }

    public function test_only_the_owner_can_share_a_folder(): void
    {
        $folder = $this->folder($this->makeUser(), 'Mine');

        $this->actingAs($this->makeUser('other'))->postJson("/uploads/folders/{$folder->id}/share")->assertJson(['status' => false]);
        $this->assertFalse($folder->fresh()->isShared());
    }

    public function test_the_gallery_covers_the_folder_and_subfolders_only(): void
    {
        $user = $this->makeUser();
        $root = $this->folder($user, 'Trip');
        $sub = $this->folder($user, 'Day 1', $root);
        $outside = $this->folder($user, 'Elsewhere');
        $this->upload($user, $root, 'top.png');
        $this->upload($user, $sub, 'deep.png');
        $this->upload($user, $outside, 'private.png');
        $this->upload($user, null, 'rootfile.png');
        $root = $this->share($user, $root);

        $this->get($root->shareUrl())->assertOk()->assertSee('top.png')->assertSee('Day 1')->assertSee('deep.png')
            ->assertDontSee('private.png')->assertDontSee('rootfile.png')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_files_download_as_attachments_and_thumbnails_are_served_only_through_the_link(): void
    {
        $user = $this->makeUser();
        $folder = $this->folder($user, 'Trip');
        $upload = $this->upload($user, $folder, 'photo.jpg');
        $other = $this->upload($user, $this->folder($user, 'Other'), 'secret.png');
        $folder = $this->share($user, $folder);
        $base = $folder->shareUrl();

        $response = $this->get("$base/{$upload->id}")->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('photo.png', $response->headers->get('Content-Disposition'), 'named after the stored format');
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->get("$base/{$upload->id}/thumb")->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->get("$base/{$other->id}")->assertNotFound();
        $this->get("$base/{$other->id}/thumb")->assertNotFound();
    }

    public function test_zip_has_every_file_with_subfolders_as_directories(): void
    {
        $user = $this->makeUser();
        $folder = $this->folder($user, 'Trip');
        $this->upload($user, $folder, 'a.png');
        $this->upload($user, $folder, 'a.png');
        $this->upload($user, $this->folder($user, 'Day 1', $folder), 'b.png');
        $folder = $this->share($user, $folder);

        $response = $this->get($folder->shareUrl().'/zip')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($path, $response->streamedContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
        unlink($path);

        $this->assertSame(['Day 1/b.png', 'a (2).png', 'a.png'], $names);
    }

    public function test_dead_links_all_look_the_same(): void
    {
        $user = $this->makeUser();
        $folder = $this->folder($user, 'Trip');
        $upload = $this->upload($user, $folder, 'a.png');
        $folder = $this->share($user, $folder);
        $old = $folder->share_token;

        $this->get('/s/'.str_repeat('a', 48))->assertNotFound()->assertSee(__('share.gone-title'));
        $this->get('/s/short')->assertNotFound();
        $this->get("/s/$old/".Str::uuid())->assertNotFound();

        $folder->update(['share_expires_at' => now()->subDays(2)]);
        $this->get("/s/$old")->assertNotFound();
        $this->get("/s/$old/{$upload->id}")->assertNotFound();
        $folder->update(['share_expires_at' => null]);
        $this->get("/s/$old")->assertOk();

        $folder = $this->share($user, $folder); // a new link replaces the old one
        $this->get("/s/$old")->assertNotFound();
        $this->get($folder->shareUrl())->assertOk();

        $this->actingAs($user)->deleteJson("/uploads/folders/{$folder->id}/share");
        auth()->logout();
        $this->get($folder->shareUrl())->assertNotFound();
    }
}
