<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionAttributeDefinition;
use App\Models\ConnectionEdge;
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

        $this->post('/connections', ['name' => 'Alex Example'])->assertRedirect();

        $connection = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Alex Example', $connection->name);

        // Encrypted at rest: raw DB value must not contain the plaintext name.
        $raw = DB::table('connections')->where('id', $connection->id)->value('name');
        $this->assertStringNotContainsString('Alex Example', $raw);

        $this->post("/connections/{$connection->id}/edges", [
            'target_type' => 'source', 'target_id' => $source->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
        ])->assertRedirect();
        $edge = ConnectionEdge::where('from_connection_id', $connection->id)->firstOrFail();
        $this->assertSame($source->id, $edge->to_source_id);

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
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Roundtrip Person']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $connection->id, 'to_source_id' => $source->id,
            'type' => ConnectionEdge::TYPE_ONE_WAY,
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
        $this->assertSame('source', $data['connections'][0]['edges'][0]['target_kind']);
        $this->assertSame('VRChat', $data['connections'][0]['edges'][0]['target_name']);
        $this->assertSame('Buddy', $data['connections'][0]['attribute_values'][0]['value']);

        // Wipe and re-import
        Connection::where('user_id', $user->id)->delete();
        \App\Models\ConnectionSource::where('user_id', $user->id)->delete();
        ConnectionAttributeDefinition::where('user_id', $user->id)->delete();

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('connections.json', $json);
        $this->post('/connections/import', ['file' => $file])->assertRedirect();

        $imported = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Roundtrip Person', $imported->name);
        $edge = $imported->edgesFrom()->firstOrFail();
        $this->assertSame('VRChat', $edge->toSource->name);
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
            'date'          => ['options' => null, 'value' => '2024-01-15'],
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

    public function test_store_edge_creates_one_way_edge_to_source()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);
        $source = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat']);

        $this->post("/connections/{$connection->id}/edges", [
            'target_type' => 'source', 'target_id' => $source->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
        ])->assertRedirect();

        $edge = ConnectionEdge::where('from_connection_id', $connection->id)->firstOrFail();
        $this->assertSame($source->id, $edge->to_source_id);
        $this->assertNull($edge->to_connection_id);
        $this->assertSame(ConnectionEdge::TYPE_ONE_WAY, $edge->type);
    }

    public function test_store_edge_supports_unlimited_edges_per_connection()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Bob']);
        $alice = Connection::create(['user_id' => $user->id, 'name' => 'Alice']);
        $carol = Connection::create(['user_id' => $user->id, 'name' => 'Carol']);

        $this->post("/connections/{$connection->id}/edges", [
            'target_type' => 'connection', 'target_id' => $alice->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
        ])->assertRedirect();
        $this->post("/connections/{$connection->id}/edges", [
            'target_type' => 'connection', 'target_id' => $carol->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
        ])->assertRedirect();

        // No "primary" edge concept - both introducer links can coexist.
        $this->assertSame(2, ConnectionEdge::where('from_connection_id', $connection->id)->count());
    }

    public function test_store_edge_rejects_self_link()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Solo']);

        $this->post("/connections/{$connection->id}/edges", [
            'target_type' => 'connection', 'target_id' => $connection->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
        ])->assertSessionHasErrors('target_id');
    }

    public function test_store_edge_rejects_duplicate_bidirectional_link_in_either_direction()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'A']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'B']);

        $this->post("/connections/{$a->id}/edges", [
            'target_type' => 'connection', 'target_id' => $b->id, 'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ])->assertRedirect();

        // Duplicate in the reverse direction is still rejected, since "know each other" is symmetric.
        $this->post("/connections/{$b->id}/edges", [
            'target_type' => 'connection', 'target_id' => $a->id, 'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ])->assertSessionHasErrors('target_id');

        $this->assertSame(1, ConnectionEdge::where('user_id', $user->id)->count());
    }

    public function test_destroy_edge_removes_edge_regardless_of_which_side_the_connection_is_on()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'A']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'B']);

        $this->post("/connections/{$a->id}/edges", [
            'target_type' => 'connection', 'target_id' => $b->id, 'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ])->assertRedirect();
        $edge = ConnectionEdge::where('user_id', $user->id)->firstOrFail();

        // Deletable from the "to" side too, not just the "from" side that created it.
        $this->delete("/connections/{$b->id}/edges/{$edge->id}")->assertRedirect();
        $this->assertNull(ConnectionEdge::find($edge->id));
    }

    public function test_source_edit_page_lists_linked_connections_and_supports_moving_and_unlinking()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $vrchat = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat']);
        $discord = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'Discord']);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);
        $edge = ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $connection->id, 'to_source_id' => $vrchat->id,
            'type' => ConnectionEdge::TYPE_ONE_WAY,
        ]);

        $this->get("/connections?source={$vrchat->id}")->assertOk()->assertSee('Alex');

        $this->put("/connections/{$connection->id}/edges/{$edge->id}", ['target_id' => $discord->id])
            ->assertRedirect();
        $this->assertSame($discord->id, $edge->fresh()->to_source_id);

        $this->delete("/connections/{$connection->id}/edges/{$edge->id}")->assertRedirect();
        $this->assertNull(ConnectionEdge::find($edge->id));
    }

    public function test_update_edge_rejects_move_that_would_duplicate_an_existing_link()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $vrchat = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'VRChat']);
        $discord = \App\Models\ConnectionSource::create(['user_id' => $user->id, 'name' => 'Discord']);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $connection->id, 'to_source_id' => $discord->id,
            'type' => ConnectionEdge::TYPE_ONE_WAY,
        ]);
        $edge = ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $connection->id, 'to_source_id' => $vrchat->id,
            'type' => ConnectionEdge::TYPE_ONE_WAY,
        ]);

        $this->put("/connections/{$connection->id}/edges/{$edge->id}", ['target_id' => $discord->id])
            ->assertSessionHasErrors('target_id');
        $this->assertSame($vrchat->id, $edge->fresh()->to_source_id);
    }

    public function test_deleting_a_connection_cascades_its_edges()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'A']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'B']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $a->id, 'to_connection_id' => $b->id,
            'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ]);

        $this->delete("/connections/{$a->id}")->assertRedirect();

        $this->assertSame(0, ConnectionEdge::where('user_id', $user->id)->count());
    }

    public function test_graph_endpoint_returns_no_names()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $a = Connection::create(['user_id' => $user->id, 'name' => 'Secret Alice']);
        $b = Connection::create(['user_id' => $user->id, 'name' => 'Secret Bob']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $b->id, 'to_connection_id' => $a->id,
            'type' => ConnectionEdge::TYPE_ONE_WAY,
        ]);
        $c = Connection::create(['user_id' => $user->id, 'name' => 'Secret Carol']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $b->id, 'to_connection_id' => $c->id,
            'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ]);

        $response = $this->getJson('/connections/graph');
        $response->assertOk();
        $body = $response->json();

        $this->assertStringNotContainsString('Secret Alice', json_encode($body));
        $this->assertStringNotContainsString('Secret Bob', json_encode($body));
        $this->assertCount(3, $body['nodes']);
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

    public function test_connman_import_creates_edges_for_one_way_and_bidirectional_links()
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

        $introducedEdge = ConnectionEdge::where('from_connection_id', $bob->id)
            ->where('to_connection_id', $alice->id)->firstOrFail();
        $this->assertSame(ConnectionEdge::TYPE_ONE_WAY, $introducedEdge->type);

        $mutualEdge = ConnectionEdge::where('type', ConnectionEdge::TYPE_BI_DIRECTIONAL)->firstOrFail();
        $this->assertContains($mutualEdge->from_connection_id, [$bob->id, $carol->id]);
        $this->assertContains($mutualEdge->to_connection_id, [$bob->id, $carol->id]);

        // The group became a source, and Carol (who linked to it) got a "met via" edge to it.
        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Some Server', $source->name);
        $this->assertSame('group', $source->category);
        $sourceEdge = ConnectionEdge::where('from_connection_id', $carol->id)->where('to_source_id', $source->id)->firstOrFail();
        $this->assertSame(ConnectionEdge::TYPE_ONE_WAY, $sourceEdge->type);
    }

    public function test_connman_import_creates_unlimited_edges_without_skipping_conflicts()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connman = [
            'people' => [
                ['id' => 'p1', 'name' => 'Alice', 'type' => 'person'],
                ['id' => 'p2', 'name' => 'Bob', 'type' => 'person'],
                ['id' => 'p3', 'name' => 'Carol', 'type' => 'person'],
                ['id' => 'g1', 'name' => 'Group One', 'type' => 'group'],
                ['id' => 'g2', 'name' => 'Group Two', 'type' => 'group'],
            ],
            'connections' => [
                // Bob is introduced through both Alice and Carol - both become edges, no primary/extra split.
                ['id' => 'e1', 'from' => 'p2', 'to' => 'p1', 'type' => 'one-way'],
                ['id' => 'e2', 'from' => 'p2', 'to' => 'p3', 'type' => 'one-way'],
                // Bob belongs to both groups - both become "met via" edges.
                ['id' => 'e3', 'from' => 'p2', 'to' => 'g1', 'type' => 'one-way'],
                ['id' => 'e4', 'from' => 'p2', 'to' => 'g2', 'type' => 'one-way'],
            ],
        ];

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $bob = Connection::where('user_id', $user->id)->get()->firstWhere('name', 'Bob');
        $alice = Connection::where('user_id', $user->id)->get()->firstWhere('name', 'Alice');
        $carol = Connection::where('user_id', $user->id)->get()->firstWhere('name', 'Carol');

        $bobEdges = ConnectionEdge::where('from_connection_id', $bob->id)->get();
        $this->assertSame(4, $bobEdges->count());
        $this->assertTrue($bobEdges->contains('to_connection_id', $alice->id));
        $this->assertTrue($bobEdges->contains('to_connection_id', $carol->id));

        $group1 = \App\Models\ConnectionSource::where('user_id', $user->id)->where('name', 'Group One')->firstOrFail();
        $group2 = \App\Models\ConnectionSource::where('user_id', $user->id)->where('name', 'Group Two')->firstOrFail();
        $this->assertTrue($bobEdges->contains('to_source_id', $group1->id));
        $this->assertTrue($bobEdges->contains('to_source_id', $group2->id));
    }

    public function test_connman_import_gives_the_group_category_a_default_color_for_the_graph()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connman = [
            'people' => [
                ['id' => 'p1', 'name' => 'Carol', 'type' => 'person'],
                ['id' => 'g1', 'name' => 'Some Server', 'type' => 'group'],
            ],
            'connections' => [
                ['id' => 'e1', 'from' => 'p1', 'to' => 'g1', 'type' => 'one-way'],
            ],
        ];

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $category = \App\Models\ConnectionSourceCategory::where('user_id', $user->id)->where('name', 'group')->firstOrFail();
        $this->assertNotNull($category->color);

        $group = \App\Models\ConnectionSource::where('user_id', $user->id)->where('name', 'Some Server')->firstOrFail();
        $body = $this->getJson('/connections/graph')->json();
        $node = collect($body['nodes'])->firstWhere('id', $group->id);
        $this->assertSame('source', $node['type']);
        $this->assertSame($category->color, $node['color']);
    }

    public function test_connman_import_does_not_override_a_user_chosen_group_color()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        \App\Models\ConnectionSourceCategory::create(['user_id' => $user->id, 'name' => 'group', 'color' => '#123456']);

        $connman = [
            'people' => [
                ['id' => 'p1', 'name' => 'Carol', 'type' => 'person'],
                ['id' => 'g1', 'name' => 'Some Server', 'type' => 'group'],
            ],
            'connections' => [
                ['id' => 'e1', 'from' => 'p1', 'to' => 'g1', 'type' => 'one-way'],
            ],
        ];

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $category = \App\Models\ConnectionSourceCategory::where('user_id', $user->id)->where('name', 'group')->firstOrFail();
        $this->assertSame('#123456', $category->color);
    }

    public function test_connman_import_wipes_existing_connections_and_edges_first()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $preExisting = Connection::create(['user_id' => $user->id, 'name' => 'Manually Added']);
        $other = Connection::create(['user_id' => $user->id, 'name' => 'Other']);
        ConnectionEdge::create([
            'user_id' => $user->id, 'from_connection_id' => $preExisting->id, 'to_connection_id' => $other->id,
            'type' => ConnectionEdge::TYPE_BI_DIRECTIONAL,
        ]);

        $connman = ['people' => [['id' => 'p1', 'name' => 'Fresh Person', 'type' => 'person']], 'connections' => []];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $this->assertSame(1, Connection::where('user_id', $user->id)->count());
        $this->assertSame('Fresh Person', Connection::where('user_id', $user->id)->first()->name);
        $this->assertSame(0, ConnectionEdge::where('user_id', $user->id)->count());
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

        // No matching token at all: a brand new one is created and linked instead of being left unlinked.
        $unrelated->refresh();
        $this->assertNotNull($unrelated->highlight_token_id);
        $newToken = \App\Models\CalendarHighlightToken::find($unrelated->highlight_token_id);
        $this->assertSame('Zzz Nomatch', $newToken->label);
        $this->assertTrue($newToken->words->pluck('word')->contains('Zzz Nomatch'));
        $this->assertTrue($newToken->archived);
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

    public function test_connman_import_does_not_create_tokens_for_unmatched_connections()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connman = ['people' => [['id' => 'p1', 'name' => 'Nobody Matches Me', 'type' => 'person']], 'connections' => []];
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('network.json', json_encode($connman));
        $this->post('/connections/import-connman', ['file' => $file])->assertRedirect();

        $imported = Connection::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($imported->highlight_token_id);
        $this->assertSame(0, \App\Models\CalendarHighlightToken::where('user_id', $user->id)->count());
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

    public function test_source_icon_can_be_uploaded_replaced_and_removed()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post('/connections/sources', ['name' => 'VRChat'])->assertRedirect();
        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();

        // Icon uploads only happen through the edit form, not the quick "add source" form.
        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat',
            'icon' => \Illuminate\Http\UploadedFile::fake()->image('icon.png'),
        ])->assertRedirect();

        $source->refresh();
        $this->assertNotNull($source->icon_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($source->icon_path);
        $firstIconPath = $source->icon_path;

        // Uploading a new icon replaces the old file.
        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat',
            'icon' => \Illuminate\Http\UploadedFile::fake()->image('icon2.png'),
        ])->assertRedirect();

        $source->refresh();
        $this->assertNotNull($source->icon_path);
        $this->assertNotSame($firstIconPath, $source->icon_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($source->icon_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($firstIconPath);

        $secondIconPath = $source->icon_path;

        // Checking "remove icon" clears it and deletes the file.
        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat', 'remove_icon' => '1',
        ])->assertRedirect();

        $source->refresh();
        $this->assertNull($source->icon_path);
        $this->assertNull($source->icon_url);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($secondIconPath);
    }

    public function test_destroying_source_deletes_its_icon_file()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post('/connections/sources', ['name' => 'VRChat'])->assertRedirect();
        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();

        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat',
            'icon' => \Illuminate\Http\UploadedFile::fake()->image('icon.png'),
        ])->assertRedirect();

        $iconPath = $source->refresh()->icon_path;

        $this->delete("/connections/sources/{$source->id}")->assertRedirect();

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($iconPath);
    }

    public function test_graph_endpoint_represents_a_source_as_a_single_node_with_its_icon()
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->post('/connections/sources', ['name' => 'VRChat'])->assertRedirect();
        $source = \App\Models\ConnectionSource::where('user_id', $user->id)->firstOrFail();
        $this->put("/connections/sources/{$source->id}", [
            'name' => 'VRChat',
            'icon' => \Illuminate\Http\UploadedFile::fake()->image('icon.png'),
        ])->assertRedirect();
        $source->refresh();

        $this->post('/connections', ['name' => 'Alex Example'])->assertRedirect();
        $this->post('/connections', ['name' => 'Blake Example'])->assertRedirect();
        $connections = Connection::where('user_id', $user->id)->get();
        $alex = $connections->firstWhere('name', 'Alex Example');
        $blake = $connections->firstWhere('name', 'Blake Example');

        foreach ([$alex, $blake] as $connection) {
            $this->post("/connections/{$connection->id}/edges", [
                'target_type' => 'source', 'target_id' => $source->id, 'type' => ConnectionEdge::TYPE_ONE_WAY,
            ])->assertRedirect();
        }

        $body = $this->get('/connections/graph')->assertOk()->json();

        // The source appears exactly once as its own "source" node, not duplicated per connection.
        $sourceNodes = collect($body['nodes'])->where('id', $source->id);
        $this->assertCount(1, $sourceNodes);
        $sourceNode = $sourceNodes->first();
        $this->assertSame('source', $sourceNode['type']);
        $this->assertSame($source->icon_url, $sourceNode['icon']);

        // Every connection has its own edge pointing into that single source node.
        $edgesToSource = collect($body['edges'])->where('to', $source->id);
        $this->assertCount(2, $edgesToSource);
        $this->assertSame(['introduced', 'introduced'], $edgesToSource->pluck('kind')->values()->toArray());
        $this->assertEqualsCanonicalizing([$alex->id, $blake->id], $edgesToSource->pluck('from')->values()->toArray());

        // Connection nodes themselves carry no color/icon of their own anymore.
        $connectionNode = collect($body['nodes'])->firstWhere('id', $alex->id);
        $this->assertSame('connection', $connectionNode['type']);
        $this->assertNull($connectionNode['icon']);
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

    public function test_date_attribute_validates_and_saves()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $definition = ConnectionAttributeDefinition::create(['user_id' => $user->id, 'label' => 'Met on', 'type' => 'date']);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);

        $this->put("/connections/{$connection->id}", [
            'name' => 'Alex', 'attributes' => [$definition->id => '2024-01-15'],
        ])->assertRedirect();
        $this->assertSame('2024-01-15', $connection->attributeValues()->firstOrFail()->typed_value);

        $this->put("/connections/{$connection->id}", [
            'name' => 'Alex', 'attributes' => [$definition->id => 'not-a-date'],
        ])->assertSessionHasErrors('attributes');
    }

    public function test_create_highlight_for_connection_is_archived_by_default()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);

        $this->post("/connections/{$connection->id}/create-highlight")->assertRedirect();

        $connection->refresh();
        $this->assertNotNull($connection->highlight_token_id);
        $token = \App\Models\CalendarHighlightToken::find($connection->highlight_token_id);
        $this->assertTrue($token->archived);
    }

    public function test_link_existing_highlight_token_syncs_its_label_to_the_connection_name()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Old Label',
        ]);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex']);

        $this->post("/connections/{$connection->id}/highlight-token/link", [
            'highlight_token_id' => $token->id,
        ])->assertRedirect();

        $this->assertSame($token->id, $connection->refresh()->highlight_token_id);
        $this->assertSame('Alex', $token->refresh()->label);
    }

    public function test_saving_connection_keeps_linked_token_label_in_sync()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alex',
        ]);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex', 'highlight_token_id' => $token->id]);

        $this->put("/connections/{$connection->id}", ['name' => 'Alexandra'])->assertRedirect();

        $this->assertSame('Alexandra', $token->refresh()->label);
    }

    public function test_unlink_highlight_token_detaches_without_deleting_it()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alex',
        ]);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex', 'highlight_token_id' => $token->id]);

        $this->post("/connections/{$connection->id}/highlight-token/unlink")->assertRedirect();

        $this->assertNull($connection->refresh()->highlight_token_id);
        $this->assertNotNull($token->fresh());
    }

    public function test_regenerate_toggle_archive_and_destroy_connection_highlight_token()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alex',
        ]);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex', 'highlight_token_id' => $token->id]);
        $originalBytes = $token->token;

        $this->post("/connections/{$connection->id}/highlight-token/regenerate")->assertRedirect();
        $this->assertNotSame($originalBytes, $token->fresh()->token);

        $this->post("/connections/{$connection->id}/highlight-token/toggle-archive")->assertRedirect();
        $this->assertTrue($token->fresh()->archived);
        $this->post("/connections/{$connection->id}/highlight-token/toggle-archive")->assertRedirect();
        $this->assertFalse($token->fresh()->archived);

        $this->delete("/connections/{$connection->id}/highlight-token")->assertRedirect();
        $this->assertNull(\App\Models\CalendarHighlightToken::find($token->id));
        // Deleting the token via nullOnDelete detaches the connection automatically.
        $this->assertNull($connection->refresh()->highlight_token_id);
    }

    public function test_store_and_destroy_connection_highlight_word()
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $token = \App\Models\CalendarHighlightToken::create([
            'user_id' => $user->id, 'token' => \App\Models\CalendarHighlightToken::generateToken(), 'label' => 'Alex',
        ]);
        $connection = Connection::create(['user_id' => $user->id, 'name' => 'Alex', 'highlight_token_id' => $token->id]);

        $this->post("/connections/{$connection->id}/highlight-token/words", ['word' => 'Alexandra'])->assertRedirect();
        $word = \App\Models\CalendarHighlightWord::where('token_id', $token->id)->where('word', 'Alexandra')->firstOrFail();

        // Duplicate word (already used by any of the user's tokens) is rejected.
        $this->post("/connections/{$connection->id}/highlight-token/words", ['word' => 'Alexandra'])
            ->assertSessionHasErrors('word');

        $this->delete("/connections/{$connection->id}/highlight-token/words/{$word->id}")->assertRedirect();
        $this->assertNull(\App\Models\CalendarHighlightWord::find($word->id));
    }
}
