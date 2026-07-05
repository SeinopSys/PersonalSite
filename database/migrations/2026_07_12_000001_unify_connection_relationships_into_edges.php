<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the "one primary field + separate extra/mutual tables" design with a single ConnMan-style
     * edges table: every relationship (met via a source, introduced by/through a connection, or a mutual
     * "know each other" link) is just a row here, with no artificial primary/extra distinction and no
     * limit on how many a connection can have. `met_at` similarly moves to being a plain "date" custom
     * attribute instead of a hardcoded column, consistent with notes/met_note/relationship_type earlier.
     * No data migration - this app has no real users yet, so existing source_id/introduced_by_connection_id/
     * met_at values are simply dropped rather than converted.
     */
    public function up(): void
    {
        Schema::table('connection_edges', function (Blueprint $table) {
            $table->renameColumn('connection_a_id', 'from_connection_id');
            $table->renameColumn('connection_b_id', 'to_connection_id');
        });

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->dropUnique('connection_edges_connection_a_id_connection_b_id_unique');
            $table->foreignUuid('to_source_id')->nullable()->after('to_connection_id')
                ->constrained('connection_sources')->cascadeOnDelete();
            $table->string('type')->default('bi_directional')->after('to_source_id');
        });

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->uuid('to_connection_id')->nullable()->change();
        });

        DB::statement('
            ALTER TABLE connection_edges
            ADD CONSTRAINT connection_edges_exactly_one_target CHECK (
                (to_connection_id IS NOT NULL)::int + (to_source_id IS NOT NULL)::int = 1
            )
        ');

        Schema::table('connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
            $table->dropConstrainedForeignId('introduced_by_connection_id');
            $table->dropColumn('met_at');
        });
    }

    /** Structure only - dropped data is not restored. */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->foreignUuid('source_id')->nullable()->constrained('connection_sources')->nullOnDelete();
            $table->foreignUuid('introduced_by_connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->text('met_at')->nullable();
        });

        DB::statement('ALTER TABLE connection_edges DROP CONSTRAINT connection_edges_exactly_one_target');

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_source_id');
            $table->dropColumn('type');
        });

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->uuid('to_connection_id')->nullable(false)->change();
        });

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->renameColumn('from_connection_id', 'connection_a_id');
            $table->renameColumn('to_connection_id', 'connection_b_id');
        });

        Schema::table('connection_edges', function (Blueprint $table) {
            $table->unique(['connection_a_id', 'connection_b_id']);
        });
    }
};
