<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Children before parents, to satisfy foreign key constraints.
        Schema::dropIfExists('connection_edges');
        Schema::dropIfExists('connection_attribute_values');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('calendar_highlight_words');
        Schema::dropIfExists('calendar_highlight_tokens');
        Schema::dropIfExists('connection_attribute_definitions');
        Schema::dropIfExists('connection_source_categories');
        Schema::dropIfExists('connection_sources');
        Schema::dropIfExists('sleep_exceptions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'calendar_url',
                'availability_settings',
                'timezone',
                'dnd_event_name',
                'nap_event_name',
            ]);
        });
    }

    public function down(): void
    {
        // Irreversible: the availability/connections/highlight-token feature has been removed from
        // the codebase, so there is nothing left to recreate these tables/columns for.
        throw new \RuntimeException('This migration cannot be reversed.');
    }
};
