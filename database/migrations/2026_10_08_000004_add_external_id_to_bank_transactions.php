<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The bank's own identifier of an imported transaction, encrypted like the rest. The index column
     * is a keyed hash that makes re-running a CSV import idempotent.
     */
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->text('external_id')->nullable();
            $table->char('external_id_index', 64)->nullable();
            $table->unique(['user_id', 'external_id_index']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'external_id_index']);
            $table->dropColumn(['external_id', 'external_id_index']);
        });
    }
};
