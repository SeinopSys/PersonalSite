<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionAttributeDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'testuser'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => "$name@example.com",
            'password' => bcrypt('password'),
            'lang'     => 'en',
            'role'     => 'user',
        ]);
    }

    public function test_user_can_create_source_and_connection_with_custom_attribute()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post('/connections/sources', ['name' => 'VRChat', 'category' => 'platform'])
            ->assertRedirect();

        $this->post('/connections/attributes', [
            'label' => 'Trust',
            'type'  => 'numeric_range',
            'min'   => 0,
            'max'   => 10,
            'step'  => 1,
        ])->assertRedirect();

        $definition = ConnectionAttributeDefinition::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('numeric_range', $definition->type);
        $this->assertEquals(['min' => 0, 'max' => 10, 'step' => 1], $definition->options);

        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();

        $this->post('/connections', [
            'name'      => 'Alex Example',
            'met_at'    => '2024-01-15',
            'source_id' => $source->id,
        ])->assertRedirect();

        $connection = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Alex Example', $connection->name);
        $this->assertSame('2024-01-15', $connection->met_at_date->format('Y-m-d'));

        // Encrypted at rest: raw DB value must not contain the plaintext name.
        $raw = DB::table('connections')->where('id', $connection->id)->value('name');
        $this->assertStringNotContainsString('Alex Example', $raw);

        $this->put("/connections/{$connection->id}", [
            'name'       => $connection->name,
            'attributes' => [$definition->id => '7'],
        ])->assertRedirect();

        $value = $connection->attributeValues()->firstOrFail();
        $this->assertSame(7, $value->typed_value);
        $rawValue = DB::table('connection_attribute_values')->where('id', $value->id)->value('value');
        $this->assertNotSame('7', $rawValue);
    }

    public function test_user_cannot_see_another_users_connections()
    {
        $owner = $this->makeUser('owner');
        $intruder = $this->makeUser('intruder');

        $connection = Connection::create(['user_id' => $owner->id, 'name' => 'Private Person']);

        $this->actingAs($intruder);

        $this->get('/connections')->assertDontSee('Private Person');
        $this->put("/connections/{$connection->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->delete("/connections/{$connection->id}")->assertNotFound();
    }

    public function test_export_import_round_trip()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $source = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat']);
        $definition = ConnectionAttributeDefinition::create([
            'user_id' => $user->id, 'label' => 'Nickname', 'type' => 'text',
        ]);
        $connection = Connection::create([
            'user_id' => $user->id, 'name' => 'Roundtrip Person', 'source_id' => $source->id,
        ]);
        $connection->attributeValues()->create([
            'attribute_definition_id' => $definition->id,
            'user_id'                 => $user->id,
            'value'                   => \App\Util\JSON::Encode('Buddy'),
        ]);

        $response = $this->get('/connections/export');
        $response->assertOk();
        $json = $response->streamedContent();
        $data = json_decode($json, true);

        $this->assertSame('Roundtrip Person', $data['connections'][0]['name']);
        $this->assertSame('VRChat', $data['connections'][0]['source']);
        $this->assertSame('Buddy', $data['connections'][0]['attribute_values'][0]['value']);

        // Wipe and re-import
        Connection::where('user_id', $user->id)->delete();
        \App\Models\ConnectionSource::where('user_id', $user->id)->delete();
        ConnectionAttributeDefinition::where('user_id', $user->id)->delete();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('connections.json', $json);
        $this->post('/connections/import', ['file' => $file])->assertRedirect();

        $imported = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Roundtrip Person', $imported->name);
        $this->assertSame('VRChat', $imported->source->name);
        $this->assertSame('Buddy', $imported->attributeValues()->firstOrFail()->typed_value);
    }

    public function test_index_renders_every_attribute_type_without_error()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Full House']);

        $types = [
            'number'        => ['options' => ['min' => 0, 'max' => 100], 'value' => 42],
            'numeric_range' => ['options' => ['min' => 0, 'max' => 10, 'step' => 1], 'value' => 5],
            'enum'          => ['options' => ['choices' => ['Red', 'Blue']], 'value' => 'Blue'],
            'radio'         => ['options' => ['choices' => ['Cat', 'Dog']], 'value' => 'Dog'],
            'text'          => ['options' => null, 'value' => 'hello'],
            'textarea'      => ['options' => null, 'value' => "hello\nworld"],
        ];

        foreach ($types as $type => $spec) {
            $definition = ConnectionAttributeDefinition::create([
                'user_id' => $user->id, 'label' => "Field $type", 'type' => $type, 'options' => $spec['options'],
            ]);
            $connection->attributeValues()->create([
                'attribute_definition_id' => $definition->id,
                'user_id'                 => $user->id,
                'value'                   => \App\Util\JSON::Encode($spec['value']),
            ]);
        }

        $this->get('/connections')->assertOk()->assertSee('Full House');
        $this->get("/connections?connection={$connection->id}")->assertOk()->assertSee('Full House');
    }

    public function test_connection_can_be_linked_to_and_unlinked_from_its_introducer()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $introducer = Connection::create(['user_id' => $user->id, 'name' => 'Alice']);
        $introduced = Connection::create(['user_id' => $user->id, 'name' => 'Bob']);

        $this->put("/connections/{$introduced->id}", [
            'name' => 'Bob',
            'introduced_by_connection_id' => $introducer->id,
        ])->assertRedirect();

        $introduced->refresh();
        $this->assertSame($introducer->id, $introduced->introduced_by_connection_id);
        $this->assertSame('Alice', $introduced->introducedBy->name);

        // A real FK with nullOnDelete: deleting the introducer clears the reference automatically.
        $this->delete("/connections/{$introducer->id}")->assertRedirect();
        $introduced->refresh();
        $this->assertNull($introduced->introduced_by_connection_id);
    }

    public function test_connection_cannot_be_introduced_by_itself()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Solo']);

        $this->put("/connections/{$connection->id}", [
            'name' => 'Solo',
            'introduced_by_connection_id' => $connection->id,
        ])->assertSessionHasErrors('introduced_by_connection_id');

        $this->assertNull($connection->refresh()->introduced_by_connection_id);
    }

    public function test_export_import_round_trip_preserves_introduced_by()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $introducer = Connection::create(['user_id' => $user->id, 'name' => 'Alice']);
        $introduced = Connection::create([
            'user_id' => $user->id, 'name' => 'Bob', 'introduced_by_connection_id' => $introducer->id,
        ]);

        $json = $this->get('/connections/export')->streamedContent();
        $data = json_decode($json, true);
        $bobExport = collect($data['connections'])->firstWhere('name', 'Bob');
        $this->assertSame('Alice', $bobExport['introduced_by_name']);

        Connection::where('user_id', $user->id)->delete();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('connections.json', $json);
        $this->post('/connections/import', ['file' => $file])->assertRedirect();

        $importedBob = Connection::where('user_id', $user->id)->get()->firstWhere('name', 'Bob');
        $this->assertNotNull($importedBob);
        $this->assertSame('Alice', $importedBob->introducedBy->name);
    }

    public function test_mutual_graph_edge_can_be_created_and_removed()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'A']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'B']);

        $this->post('/connections/graph-edges', [
            'connection_a_id' => $a->id, 'connection_b_id' => $b->id,
        ])->assertRedirect();

        $edge = \App\Models\ConnectionEdge::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($a->id, $edge->connection_a_id);
        $this->assertSame($b->id, $edge->connection_b_id);

        // Duplicate in either order is rejected.
        $this->post('/connections/graph-edges', [
            'connection_a_id' => $b->id, 'connection_b_id' => $a->id,
        ])->assertSessionHasErrors('connection_b_id');
        $this->assertSame(1, \App\Models\ConnectionEdge::where('user_id', $user->id)->count());

        $this->delete("/connections/graph-edges/{$edge->id}")->assertRedirect();
        $this->assertSame(0, \App\Models\ConnectionEdge::where('user_id', $user->id)->count());
    }

    public function test_graph_endpoint_returns_no_names()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'Secret Alice']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'Secret Bob', 'introduced_by_connection_id' => $a->id]);
        \App\Models\ConnectionEdge::create(['user_id' => $user->id, 'connection_a_id' => $a->id, 'connection_b_id' => $b->id]);

        $response = $this->getJson('/connections/graph');
        $response->assertOk();
        $body = $response->json();

        $this->assertStringNotContainsString('Secret Alice', json_encode($body));
        $this->assertStringNotContainsString('Secret Bob', json_encode($body));
        $this->assertCount(2, $body['nodes']);
        $kinds = collect($body['edges'])->pluck('kind')->sort()->values()->toArray();
        $this->assertSame(['introduced', 'mutual'], $kinds);
    }

    public function test_graph_seed_is_stable_across_requests_for_the_same_user()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $first = $this->getJson('/connections/graph')->json();
        $second = $this->getJson('/connections/graph')->json();

        $this->assertSame($first['seed'], $second['seed']);
        $this->assertIsInt($first['seed']);
    }

    public function test_connman_import_maps_one_way_to_introduced_by_and_bidirectional_to_mutual_edge()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connman = [
            'people' => [
                ['id' => 'p1', 'name' => 'Alice', 'type' => 'person'],
                ['id' => 'p2', 'name' => 'Bob', 'type' => 'person'],
                ['id' => 'p3', 'name' => 'Carol', 'type' => 'person'],
                ['id' => 'g1', 'name' => 'Some Server', 'type' => 'group'],
            ],
            'connections' => [
                ['id' => 'e1', 'from' => 'p2', 'to' => 'p1', 'type' => 'one-way'],
                ['id' => 'e2', 'from' => 'p2', 'to' => 'p3', 'type' => 'bi-directional'],
                ['id' => 'e3', 'from' => 'p3', 'to' => 'g1', 'type' => 'one-way'],
            ],
        ];

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $this->assertSame(3, Connection::where('user_id', $user->id)->count());

        $all = Connection::where('user_id', $user->id)->get();
        $bob = $all->firstWhere('name', 'Bob');
        $alice = $all->firstWhere('name', 'Alice');
        $carol = $all->firstWhere('name', 'Carol');

        $this->assertSame($alice->id, $bob->introduced_by_connection_id);

        $edge = \App\Models\ConnectionEdge::where('user_id', $user->id)->firstOrFail();
        $this->assertContains($edge->connection_a_id, [$bob->id, $carol->id]);
        $this->assertContains($edge->connection_b_id, [$bob->id, $carol->id]);
        $this->assertSame(1, \App\Models\ConnectionEdge::where('user_id', $user->id)->count());

        // The group became a source, and Carol (who linked to it) got it as her "met via" source.
        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Some Server', $source->name);
        $this->assertSame('group', $source->category);
        $this->assertSame($source->id, $carol->refresh()->source_id);
    }

    public function test_connman_import_wipes_existing_connections_and_edges_first()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $preExisting = Connection::create(['user_id' => $user->id, 'name' => 'Manually Added']);
        $other = Connection::create(['user_id' => $user->id, 'name' => 'Other']);
        \App\Models\ConnectionEdge::create(['user_id' => $user->id, 'connection_a_id' => $preExisting->id, 'connection_b_id' => $other->id]);

        $connman = ['people' => [['id' => 'p1', 'name' => 'Fresh Person', 'type' => 'person']], 'connections' => []];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $this->assertSame(1, Connection::where('user_id', $user->id)->count());
        $this->assertSame('Fresh Person', Connection::where('user_id', $user->id)->first()->name);
        $this->assertSame(0, \App\Models\ConnectionEdge::where('user_id', $user->id)->count());
    }

    public function test_auto_link_highlight_tokens_by_name()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alice',
        ]);
        \App\Models\CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $ambiguousToken = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Bobby',
        ]);
        \App\Models\CalendarHighlightWord::create(['token_id' => $ambiguousToken->id, 'user_id' => $user->id, 'word' => 'Bob']);
        $otherBobToken = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Bobcat',
        ]);
        // Two distinct words that both match "Bob" via substring containment - a word can only belong to
        // one token per user (unique constraint), so ambiguity has to come from two different words.
        \App\Models\CalendarHighlightWord::create(['token_id' => $otherBobToken->id, 'user_id' => $user->id, 'word' => 'Bobby']);

        $alice = Connection::create(['user_id' => $user->id, 'name' => 'Alice']);
        $bob = Connection::create(['user_id' => $user->id, 'name' => 'Bob']); // matches two tokens - ambiguous
        $unrelated = Connection::create(['user_id' => $user->id, 'name' => 'Zzz Nomatch']);

        $this->post('/connections/auto-link-highlights')->assertRedirect();

        $this->assertSame($token->id, $alice->refresh()->highlight_token_id);
        $this->assertNull($bob->refresh()->highlight_token_id);
        $this->assertNull($unrelated->refresh()->highlight_token_id);
    }

    public function test_auto_link_never_changes_an_already_linked_connection()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $originalToken = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Original',
        ]);
        $matchingToken = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alice',
        ]);
        \App\Models\CalendarHighlightWord::create(['token_id' => $matchingToken->id, 'user_id' => $user->id, 'word' => 'Alice']);

        // Already linked to a token whose words don't even match the name - auto-link must leave it alone.
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alice', 'highlight_token_id' => $originalToken->id]);

        $this->post('/connections/auto-link-highlights')->assertRedirect();

        $this->assertSame($originalToken->id, $connection->refresh()->highlight_token_id);
    }

    public function test_connman_import_also_auto_links_highlight_tokens()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alice',
        ]);
        \App\Models\CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'Alice']);

        $connman = ['people' => [['id' => 'p1', 'name' => 'Alice', 'type' => 'person']], 'connections' => []];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $imported = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($token->id, $imported->highlight_token_id);
    }

    public function test_connman_import_renames_connection_to_matched_highlight_token_label()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alice Realname',
        ]);
        \App\Models\CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $user->id, 'word' => 'xXAlice_VRxX']);

        $connman = ['people' => [['id' => 'p1', 'name' => 'xXAlice_VRxX', 'type' => 'person']], 'connections' => []];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $imported = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($token->id, $imported->highlight_token_id);
        $this->assertSame('Alice Realname', $imported->name);
    }

    public function test_source_can_be_renamed_and_recategorized()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $source = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat', 'category' => 'platform']);

        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat (renamed)', 'category' => 'game',
        ])->assertRedirect();

        $source->refresh();
        $this->assertSame('VRChat (renamed)', $source->name);
        $this->assertSame('game', $source->category);
    }

    public function test_source_category_color_can_be_set_from_the_source_edit_form()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $source = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat', 'category' => 'platform']);

        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat', 'category' => 'platform', 'color' => '#2a78d6',
        ])->assertRedirect();

        $category = \App\Models\ConnectionSourceCategory::where('user_id', $user->id)->where('name', 'platform')->firstOrFail();
        $this->assertSame('#2a78d6', $category->color);

        // Saving again for the same category updates rather than duplicating.
        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat', 'category' => 'platform', 'color' => '#e34948',
        ])->assertRedirect();
        $this->assertSame(1, \App\Models\ConnectionSourceCategory::where('user_id', $user->id)->count());
        $this->assertSame('#e34948', $category->refresh()->color);
    }

    public function test_index_lists_connections_alphabetically_and_only_selected_gets_full_detail()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $zed = Connection::create(['user_id' => $user->id, 'name' => 'Zed']);
        $alice = Connection::create(['user_id' => $user->id, 'name' => 'Alice']);

        $response = $this->get('/connections');
        $response->assertOk();
        $content = $response->getContent();
        // Alice (alphabetically first) appears before Zed in the list, regardless of creation order.
        $this->assertLessThan(strpos($content, 'Zed'), strpos($content, 'Alice'));

        // No connection selected: no detail form fields (e.g. the "Notes" placeholder) rendered.
        $response->assertDontSee('Recent/upcoming calendar matches');

        $this->get("/connections?connection={$alice->id}")->assertOk()->assertSee('Alice');
    }

    public function test_consolidated_update_clears_attribute_value_when_submitted_blank()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $definition = ConnectionAttributeDefinition::create(['user_id' => $user->id, 'label' => 'Nickname', 'type' => 'text']);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);
        $connection->attributeValues()->create([
            'attribute_definition_id' => $definition->id, 'user_id' => $user->id, 'value' => \App\Util\JSON::Encode('Lexi'),
        ]);

        $this->put("/connections/{$connection->id}", [
            'name'       => 'Alex',
            'attributes' => [$definition->id => ''],
        ])->assertRedirect();

        $this->assertSame(0, $connection->attributeValues()->count());
    }

    public function test_source_list_only_shows_detail_form_for_the_selected_source()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $vrchat = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat', 'category' => 'platform']);
        \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'Discord', 'category' => 'platform']);

        $response = $this->get('/connections');
        $response->assertOk()->assertSee('VRChat')->assertSee('Discord');
        $response->assertSee('Select a source from the list, or add a new one.');

        $this->get("/connections?source={$vrchat->id}")->assertOk()->assertSee('VRChat');
    }

    public function test_boolean_attribute_saves_checked_and_unchecked_states()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $definition = ConnectionAttributeDefinition::create(['user_id' => $user->id, 'label' => 'Verified', 'type' => 'boolean']);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);

        // Checkbox checked: browser sends both the hidden "0" and the checkbox's "1", last wins.
        $this->put("/connections/{$connection->id}", [
            'name' => 'Alex', 'attributes' => [$definition->id => '1'],
        ])->assertRedirect();
        $this->assertSame(true, $connection->attributeValues()->firstOrFail()->typed_value);

        // Unchecked: only the hidden "0" is submitted.
        $this->put("/connections/{$connection->id}", [
            'name' => 'Alex', 'attributes' => [$definition->id => '0'],
        ])->assertRedirect();
        $this->assertSame(false, $connection->attributeValues()->firstOrFail()->fresh()->typed_value);
    }
}
