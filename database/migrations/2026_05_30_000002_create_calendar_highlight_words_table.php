<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_highlight_words', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('token_id')->references('id')->on('calendar_highlight_tokens')->cascadeOnDelete();
            $table->string('word');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_highlight_words');
    }
};
