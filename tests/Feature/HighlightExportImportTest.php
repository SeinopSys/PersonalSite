<?php

namespace Tests\Feature;

use App\Models\CalendarHighlightToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HighlightExportImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'devuser', 'email' => 'dev@example.com', 'password' => bcrypt('password'),
            'lang' => 'en', 'role' => 'developer',
        ]);
    }

    public function test_export_import_round_trip_preserves_archived_status()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => CalendarHighlightToken::generateToken(), 'label' => 'Archived one', 'archived' => true,
        ]);

        $json = $this->get('/dashboard/highlights/export')->streamedContent();
        $data = json_decode($json, true);
        $exported = collect($data)->firstWhere('label', 'Archived one');
        $this->assertTrue($exported['archived']);

        CalendarHighlightToken::where('user_id', $user->id)->delete();

        $file = UploadedFile::fake()->createWithContent('highlights.json', $json);
        $this->post('/dashboard/highlights/import', ['file' => $file])->assertRedirect();

        $imported = CalendarHighlightToken::where('user_id', $user->id)->firstOrFail();
        $this->assertTrue($imported->archived);
    }

    public function test_create_connection_for_highlight_links_a_new_connection()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => CalendarHighlightToken::generateToken(), 'label' => 'Alice',
        ]);

        $this->post("/dashboard/highlights/{$token->id}/create-connection")->assertRedirect();

        $connection = \App\Models\Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Alice', $connection->name);
        $this->assertSame($token->id, $connection->highlight_token_id);

        // Calling it again for an already-linked token doesn't create a duplicate.
        $this->post("/dashboard/highlights/{$token->id}/create-connection")->assertRedirect();
        $this->assertSame(1, \App\Models\Connection::where('user_id', $user->id)->count());
    }
}
