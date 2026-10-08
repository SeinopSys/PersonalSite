<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part of a bill settled by credit from an earlier overpayment rather than by a transfer (the landlord carried
     * the overpaid amount over to this bill). Subtracted from the bill's payable amount when it is compared with the
     * transfer that paid it. Encrypted like the rest; NULL means none.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->text('credit_applied')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('credit_applied');
        });
    }
};
