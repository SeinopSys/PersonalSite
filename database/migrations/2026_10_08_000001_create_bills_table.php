<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything describing a bill is stored encrypted with the app key (see the casts on App\Models\Bill),
     * so the *_index columns hold keyed hashes used only to detect duplicates.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->text('type');
            $table->text('sha256')->nullable();
            $table->char('sha256_index', 64)->nullable();
            $table->text('invoice_number')->nullable();
            $table->char('invoice_number_index', 64)->nullable();
            $table->text('period_start');
            $table->text('period_end');
            $table->text('due_date')->nullable();
            $table->text('amount');
            $table->timestamps();

            $table->unique(['user_id', 'sha256_index']);
            $table->unique(['user_id', 'invoice_number_index']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
