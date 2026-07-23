<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webpatser\Uuid\Uuid;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_upload_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('folder_id')->nullable()->constrained('upload_folders')->cascadeOnDelete();
            $table->text('upload_key')->unique();
            $table->timestamps();
        });

        // Each user has at most one root key (folder_id NULL), and each folder has at most one key.
        DB::statement('CREATE UNIQUE INDEX image_upload_keys_root_unique ON image_upload_keys (user_id) WHERE folder_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX image_upload_keys_folder_unique ON image_upload_keys (folder_id) WHERE folder_id IS NOT NULL');

        // Migrate the existing single key-per-user rows forward non-destructively. The old
        // image_uploads table is left in place afterwards as a rollback safety net.
        foreach (DB::table('image_uploads')->get() as $row) {
            DB::table('image_upload_keys')->insert([
                'id' => (string) Uuid::generate(4),
                'user_id' => $row->user_id,
                'folder_id' => null,
                'upload_key' => $row->upload_key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('image_upload_keys');
    }
};
