<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->boolean('disable_thumbnails')->default(false);
            $table->boolean('disable_conversion')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'parent_id', 'name']);
        });

        // Added as a separate statement: the primary key on "id" is only added after the create
        // table's inline column/constraint commands run, so a self-referencing FK inline above
        // would fail with "no unique constraint matching given keys" on Postgres.
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('upload_folders')->cascadeOnDelete();
        });

        // Postgres unique indexes treat NULL as distinct, so the composite unique above doesn't
        // cover root-level siblings (parent_id IS NULL) - this partial index closes that gap.
        DB::statement('CREATE UNIQUE INDEX upload_folders_root_name_unique ON upload_folders (user_id, name) WHERE parent_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('upload_folders');
    }
};
