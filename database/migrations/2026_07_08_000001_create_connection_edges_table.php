<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mutual ("we know each other, direction unclear") edges between two connections.
        // Directional "met through" edges are modeled by connections.introduced_by_connection_id instead.
        Schema::create('connection_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('connection_a_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignUuid('connection_b_id')->constrained('connections')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['connection_a_id', 'connection_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_edges');
    }
};
