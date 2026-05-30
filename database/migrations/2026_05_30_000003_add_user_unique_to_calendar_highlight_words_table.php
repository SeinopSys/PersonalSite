<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_highlight_words', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('token_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Backfill user_id from the parent token
        DB::statement('
            UPDATE calendar_highlight_words w
            SET user_id = t.user_id
            FROM calendar_highlight_tokens t
            WHERE w.token_id = t.id
        ');

        Schema::table('calendar_highlight_words', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_highlight_words', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'word']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
