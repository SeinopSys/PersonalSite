<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks advance invoices (részszámla): a short, estimated charge inside the period a later settlement bill
     * covers in full. Their period overlapping the settlement is expected, so it isn't reported. Encrypted like the
     * rest; NULL reads as false.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->text('advance')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('advance');
        });
    }
};
