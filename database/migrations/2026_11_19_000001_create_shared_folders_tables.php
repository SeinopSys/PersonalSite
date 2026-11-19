<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Folders of files the owner shares through a secret link. The secret is stored encrypted (so the link can be
     * shown again) and a keyed hash of it is what a visit is looked up by; regenerating it revokes the old link.
     * The files themselves live on the private "shared" disk under a random name.
     */
    public function up(): void
    {
        Schema::create('shared_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('token');
            $table->char('token_index', 64)->unique();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('shared_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shared_folder_id')->references('id')->on('shared_folders')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('storage_name', 64)->unique();
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('shared_folder_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_files');
        Schema::dropIfExists('shared_folders');
    }
};
