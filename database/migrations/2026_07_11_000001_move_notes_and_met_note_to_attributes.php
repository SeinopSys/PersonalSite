<?php

use App\Util\JSON;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * "Notes", "How you met", and "Relationship" stop being hardcoded connection fields - like everything
     * else that isn't a real relation, they belong in the user-configurable custom attribute system
     * instead. Any existing data is migrated into per-user attribute definitions/values before the
     * columns are dropped, so nothing already entered is lost.
     *
     * Written against DB::table() rather than the (since-removed) Connection/ConnectionAttribute* Eloquent
     * models, so this historical migration keeps working on fresh installs after the connections feature
     * itself was deleted from the codebase.
     */
    public function up(): void
    {
        $connections = DB::table('connections')
            ->whereNotNull('notes')
            ->orWhereNotNull('met_note')
            ->orWhereNotNull('relationship_type')
            ->get();

        $defsByUserAndLabel = [];

        $migrate = function (object $connection, string $column, string $label, string $type) use (&$defsByUserAndLabel) {
            if (empty($connection->$column)) {
                return;
            }

            $userId = $connection->user_id;
            $defKey = $userId . ':' . $label;
            if (!isset($defsByUserAndLabel[$defKey])) {
                $defId = DB::table('connection_attribute_definitions')
                    ->where('user_id', $userId)
                    ->where('label', $label)
                    ->value('id');
                if ($defId === null) {
                    $defId = (string)Str::uuid();
                    DB::table('connection_attribute_definitions')->insert([
                        'id' => $defId,
                        'user_id' => $userId,
                        'label' => $label,
                        'type' => $type,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $defsByUserAndLabel[$defKey] = $defId;
            }
            $definitionId = $defsByUserAndLabel[$defKey];

            $existingId = DB::table('connection_attribute_values')
                ->where('connection_id', $connection->id)
                ->where('attribute_definition_id', $definitionId)
                ->value('id');

            $value = JSON::Encode($connection->$column);
            if ($existingId !== null) {
                DB::table('connection_attribute_values')->where('id', $existingId)->update([
                    'user_id' => $userId,
                    'value' => $value,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('connection_attribute_values')->insert([
                    'id' => (string)Str::uuid(),
                    'connection_id' => $connection->id,
                    'attribute_definition_id' => $definitionId,
                    'user_id' => $userId,
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        };

        foreach ($connections as $connection) {
            $migrate($connection, 'notes', 'Notes', 'textarea');
            $migrate($connection, 'met_note', 'How you met', 'textarea');
            $migrate($connection, 'relationship_type', 'Relationship', 'text');
        }

        Schema::table('connections', function (Blueprint $table) {
            $table->dropColumn(['notes', 'met_note', 'relationship_type']);
        });
    }

    /**
     * Structure only - migrating attribute values back into these columns isn't attempted, since the
     * corresponding attribute definitions could have since been renamed, retyped, or deleted.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('name');
            $table->string('relationship_type')->nullable()->after('notes');
            $table->text('met_note')->nullable()->after('source_id');
        });
    }
};
