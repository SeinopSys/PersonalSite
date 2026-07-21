<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connection_sources', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('connection_sources', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};
