<?php

namespace Tests\Feature;

use App\Models\SharedFile;
use App\Models\SharedFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedFoldersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('shared');
    }

    private function makeUser(string $name = 'owner'): User
    {
        return User::create([
            'name' => $name, 'email' => "$name@example.com", 'password' => bcrypt('password'), 'lang' => 'en', 'role' => 'user',
        ]);
    }

    private function folderWithFile(User $user, string $content = 'hello', string $name = 'a.txt'): array
    {
        $folder = SharedFolder::create(['user_id' => $user->id, 'name' => 'Pics', 'token' => SharedFolder::makeToken()]);
        $this->actingAs($user)->post("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->createWithContent($name, $content)]])->assertRedirect();
        auth()->logout();

        return [$folder->fresh(), $folder->files()->firstOrFail()];
    }

    public function test_owner_pages_require_login(): void
    {
        $this->get('/shared-folders')->assertRedirect('/login');
        $this->post('/shared-folders', ['name' => 'x'])->assertRedirect('/login');
    }

    public function test_creating_a_folder_makes_a_long_random_token(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post('/shared-folders', ['name' => 'Trip'])->assertRedirect();
        $this->actingAs($user)->post('/shared-folders', ['name' => 'Trip 2', 'expires_in_days' => 7])->assertRedirect();

        [$a, $b] = SharedFolder::orderBy('name')->get();
        $this->assertSame(SharedFolder::TOKEN_LENGTH, strlen($a->token));
        $this->assertNotSame($a->token, $b->token);
        $this->assertNull($a->expires_at);
        $this->assertTrue($b->expires_at->isSameDay(now()->addDays(7)));
        $this->assertNotSame($a->token, \DB::table('shared_folders')->where('id', $a->id)->value('token'), 'stored encrypted');
    }

    public function test_visitor_sees_the_list_and_downloads_a_file_without_logging_in(): void
    {
        [$folder, $file] = $this->folderWithFile($this->makeUser(), 'hello', 'a.txt');

        $this->get("/s/{$folder->token}")->assertOk()->assertSee('a.txt')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response = $this->get("/s/{$folder->token}/{$file->id}")->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('hello', $response->streamedContent());
    }

    public function test_bad_expired_and_regenerated_links_all_look_the_same(): void
    {
        $user = $this->makeUser();
        [$folder, $file] = $this->folderWithFile($user);
        $old = $folder->token;

        $this->get('/s/'.str_repeat('a', 48))->assertNotFound()->assertSee(__('share.gone-title'));
        $this->get('/s/short')->assertNotFound();
        $this->get("/s/$old/".\Illuminate\Support\Str::uuid())->assertNotFound();

        $folder->update(['expires_at' => now()->subDays(2)]);
        $this->get("/s/$old")->assertNotFound();
        $this->get("/s/$old/{$file->id}")->assertNotFound();
        $folder->update(['expires_at' => null]);
        $this->get("/s/$old")->assertOk();

        $this->actingAs($user)->post("/shared-folders/{$folder->id}/regen")->assertRedirect();
        auth()->logout();
        $this->get("/s/$old")->assertNotFound();
        $this->get('/s/'.$folder->fresh()->token)->assertOk();
    }

    public function test_a_file_cannot_be_fetched_through_another_folders_link(): void
    {
        [, $file] = $this->folderWithFile($this->makeUser());
        $other = SharedFolder::create(['user_id' => $this->makeUser('other')->id, 'name' => 'Other', 'token' => SharedFolder::makeToken()]);

        $this->get("/s/{$other->token}/{$file->id}")->assertNotFound();
    }

    public function test_zip_contains_every_file_even_with_duplicate_names(): void
    {
        $user = $this->makeUser();
        [$folder] = $this->folderWithFile($user, 'one', 'a.txt');
        $this->actingAs($user)->post("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->createWithContent('a.txt', 'two')]]);
        auth()->logout();

        $response = $this->get("/s/{$folder->token}/zip")->assertOk();
        $zip = new \ZipArchive();
        $path = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($path, $response->streamedContent());
        $this->assertTrue($zip->open($path));
        $this->assertSame(['a (2).txt', 'a.txt'], collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all());
        unlink($path);
    }

    public function test_owners_cannot_touch_each_others_folders(): void
    {
        [$folder, $file] = $this->folderWithFile($this->makeUser());
        $intruder = $this->makeUser('intruder');

        $this->actingAs($intruder)->getJson("/shared-folders/{$folder->id}")->assertNotFound();
        $this->actingAs($intruder)->deleteJson("/shared-folders/{$folder->id}/files/{$file->id}")->assertNotFound();
        $this->actingAs($intruder)->deleteJson("/shared-folders/{$folder->id}")->assertNotFound();
        $this->assertSame(1, SharedFile::count());
    }

    public function test_deleting_removes_the_stored_files(): void
    {
        $user = $this->makeUser();
        [$folder, $file] = $this->folderWithFile($user);
        Storage::disk('shared')->assertExists($file->storage_name);

        $this->actingAs($user)->delete("/shared-folders/{$folder->id}/files/{$file->id}")->assertRedirect();
        Storage::disk('shared')->assertMissing($file->storage_name);

        $this->actingAs($user)->post("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('b.bin', 1)]]);
        $stored = SharedFile::firstOrFail()->storage_name;
        $this->actingAs($user)->delete("/shared-folders/{$folder->id}")->assertRedirect('/shared-folders');
        Storage::disk('shared')->assertMissing($stored);
        $this->assertSame(0, SharedFile::count());
    }

    public function test_upload_respects_quota_and_cleans_the_name(): void
    {
        config(['app.upload_quota_bytes' => 100]);
        $user = $this->makeUser();
        $folder = SharedFolder::create(['user_id' => $user->id, 'name' => 'F', 'token' => SharedFolder::makeToken()]);

        $this->actingAs($user)->post("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('big.bin', 1)]])->assertSessionHasErrors('files');
        $this->assertSame(0, SharedFile::count());

        config(['app.upload_quota_bytes' => 10_000_000]);
        $this->actingAs($user)->post("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('..\\..\\evil/../x.txt', 1)]])->assertSessionHasNoErrors();
        $this->assertSame('x.txt', SharedFile::firstOrFail()->name);
    }

    public function test_owner_pages_render(): void
    {
        $this->instance(\Illuminate\Foundation\Mix::class, fn () => ''); // the built assets aren't there in tests
        $user = $this->makeUser();
        [$folder] = $this->folderWithFile($user, 'x', 'report.pdf');

        $this->actingAs($user)->get('/shared-folders')->assertOk()->assertSee('Pics');
        $this->actingAs($user)->get("/shared-folders/{$folder->id}")->assertOk()->assertSee('report.pdf')->assertSee($folder->token);
        $this->actingAs($user)->put("/shared-folders/{$folder->id}", ['name' => 'Renamed', 'expiry' => 'days:30'])->assertRedirect();
        $this->assertSame('Renamed', $folder->fresh()->name);
        $this->assertTrue($folder->fresh()->expires_at->isSameDay(now()->addDays(30)));
    }

    public function test_the_page_uploads_one_file_per_request_and_reads_json(): void
    {
        $user = $this->makeUser();
        $folder = SharedFolder::create(['user_id' => $user->id, 'name' => 'F', 'token' => SharedFolder::makeToken()]);

        $this->actingAs($user)->postJson("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('a.bin', 1)]])
            ->assertOk()->assertJson(['status' => true, 'count' => 1]);
        $this->actingAs($user)->postJson("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('b.bin', 1)]])->assertOk();
        $this->assertSame(2, SharedFile::count());

        config(['app.upload_max_file_kb' => 1]); // lowers the limit below PHP's
        $this->actingAs($user)->postJson("/shared-folders/{$folder->id}/files", ['files' => [UploadedFile::fake()->create('big.bin', 5)]])->assertStatus(422);
        $this->assertSame(2, SharedFile::count());
    }
}
