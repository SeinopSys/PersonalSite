<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_highlight_tokens', function (Blueprint $table) {
            $table->boolean('bypass_dnd')->default(false)->after('archived');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_highlight_tokens', function (Blueprint $table) {
            $table->dropColumn('bypass_dnd');
        });
    }
};
