<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An uploads folder can be shared through a secret link. As with the bills share links the secret is stored
     * encrypted (so the link can be shown again) and a keyed hash of it is what a visit is looked up by; the link
     * covers the folder and its subfolders. This replaces the short-lived separate "shared folders" tables.
     */
    public function up(): void
    {
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->text('share_token')->nullable();
            $table->char('share_token_index', 64)->nullable()->unique();
            $table->date('share_expires_at')->nullable();
        });

        Schema::dropIfExists('shared_files');
        Schema::dropIfExists('shared_folders');
    }

    public function down(): void
    {
        Schema::table('upload_folders', function (Blueprint $table) {
            $table->dropColumn(['share_token', 'share_token_index', 'share_expires_at']);
        });
    }
};
