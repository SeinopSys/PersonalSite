<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_bank_transaction', function (Blueprint $table) {
            $table->foreignUuid('bill_id')->references('id')->on('bills')->cascadeOnDelete();
            $table->foreignUuid('bank_transaction_id')->references('id')->on('bank_transactions')->cascadeOnDelete();

            $table->primary(['bill_id', 'bank_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_bank_transaction');
    }
};
