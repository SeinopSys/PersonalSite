<?php

use App\Models\Connection;
use App\Models\ConnectionAttributeDefinition;
use App\Models\ConnectionAttributeValue;
use App\Util\JSON;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Notes", "How you met", and "Relationship" stop being hardcoded connection fields - like everything
     * else that isn't a real relation, they belong in the user-configurable custom attribute system
     * instead. Any existing data is migrated into per-user attribute definitions/values before the
     * columns are dropped, so nothing already entered is lost.
     */
    public function up(): void
    {
        Connection::whereNotNull('notes')
            ->orWhereNotNull('met_note')
            ->orWhereNotNull('relationship_type')
            ->get()
            ->groupBy('user_id')
            ->each(function ($connections, $userId) {
                $defs = [];
                $migrate = function (Connection $connection, string $column, string $label, string $type) use ($userId, &$defs) {
                    if (empty($connection->$column)) {
                        return;
                    }
                    $defs[$label] ??= ConnectionAttributeDefinition::firstOrCreate(
                        ['user_id' => $userId, 'label' => $label],
                        ['type' => $type]
                    );
                    ConnectionAttributeValue::updateOrCreate(
                        ['connection_id' => $connection->id, 'attribute_definition_id' => $defs[$label]->id],
                        ['user_id' => $userId, 'value' => JSON::Encode($connection->$column)]
                    );
                };

                foreach ($connections as $connection) {
                    $migrate($connection, 'notes', 'Notes', 'textarea');
                    $migrate($connection, 'met_note', 'How you met', 'textarea');
                    $migrate($connection, 'relationship_type', 'Relationship', 'text');
                }
            });

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
