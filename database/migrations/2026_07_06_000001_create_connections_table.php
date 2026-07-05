<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('name');
            $table->text('notes')->nullable();
            $table->string('relationship_type')->nullable();
            $table->text('met_at')->nullable();
            $table->text('met_note')->nullable();
            $table->foreignUuid('source_id')->nullable()->constrained('connection_sources')->nullOnDelete();
            $table->foreignUuid('highlight_token_id')->nullable()->constrained('calendar_highlight_tokens')->nullOnDelete();
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
