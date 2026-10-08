<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modification date of the uploaded file as reported by the browser, encrypted like the other bill fields.
     * Used to suggest which bank transaction paid the bill.
     */
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->text('file_modified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('file_modified_at');
        });
    }
};
