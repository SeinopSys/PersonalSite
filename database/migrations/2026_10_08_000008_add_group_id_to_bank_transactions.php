<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transactions sharing a group id are treated as one payment (a bill paid in instalments or split across
     * transfers): the fee and unaccounted checks use the group's totals. Like the bill link table, it only holds
     * random identifiers, so it is not encrypted.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('group_id');
        });
    }
};
