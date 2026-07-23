<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->boolean('secondary_domain')->default(false)->after('disable_conversion');
        });
    }

    public function down(): void
    {
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->dropColumn('secondary_domain');
        });
    }
};
