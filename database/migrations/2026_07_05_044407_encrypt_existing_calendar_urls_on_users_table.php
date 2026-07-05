<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Encryption\DecryptException;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('calendar_url')->orderBy('id')->each(function ($user) {
            try {
                Crypt::decryptString($user->calendar_url);

                return;
            } catch (DecryptException) {
                // Not yet encrypted, fall through.
            }

            DB::table('users')->where('id', $user->id)->update([
                'calendar_url' => Crypt::encryptString($user->calendar_url),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereNotNull('calendar_url')->orderBy('id')->each(function ($user) {
            try {
                $decrypted = Crypt::decryptString($user->calendar_url);
            } catch (DecryptException) {
                return;
            }

            DB::table('users')->where('id', $user->id)->update([
                'calendar_url' => $decrypted,
            ]);
        });
    }
};
