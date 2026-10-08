<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part of a transaction explained by something other than a bill (a one-off), deducted before the transfer fee
     * and the unaccounted amount are worked out. Encrypted like the rest; NULL means none.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->text('accounted_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('accounted_amount');
        });
    }
};
