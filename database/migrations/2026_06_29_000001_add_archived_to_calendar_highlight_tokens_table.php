<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_highlight_tokens', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_highlight_tokens', function (Blueprint $table) {
            $table->dropColumn('archived');
        });
    }
};
