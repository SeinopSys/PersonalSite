<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks a transaction as known to be accounted for (a one-off not tied to any bill), so it is skipped by the
     * unaccounted-amount check. Encrypted like the rest; NULL reads as false.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->text('accounted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('accounted');
        });
    }
};
