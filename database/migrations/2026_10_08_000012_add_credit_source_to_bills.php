<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The invoice whose overpayment a bill's credit came from. Together with the credit amount it settles that
     * overpayment, so the overpaid invoice no longer counts as paid more than once. Like the transaction links, it only
     * holds an identifier.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignUuid('credit_source_id')->nullable()->references('id')->on('bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_source_id');
        });
    }
};
