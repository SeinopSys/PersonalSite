<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public read-only links to the bills overview. The secret itself is stored encrypted (so the link can be shown
     * again), and a keyed hash of it is what a visit is looked up by. Deleting a row revokes the link.
     */
    public function up(): void
    {
        Schema::create('share_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->text('token');
            $table->char('token_index', 64)->unique();
            $table->text('label')->nullable();
            $table->text('expires_at')->nullable();
            $table->text('view_count')->nullable();
            $table->text('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_links');
    }
};
