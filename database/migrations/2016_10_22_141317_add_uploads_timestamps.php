<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUploadsTimestamps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->timestampTz('uploaded_at')->default(DB::raw('NOW()'))->nullable();
            $table->renameColumn('uploader', 'uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('uploads', function ($table) {
            $table->dropColumn(['uploaded']);
            $table->renameColumn('uploaded_by', 'uploader');
        });
    }
}
