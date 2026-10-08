<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Util\DataTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportBillsData extends Command
{
    protected $signature = 'bills:import
                            {user : Email or ID of the user to import into}
                            {file : An export written by bills:export}
                            {--passphrase-file= : Read the passphrase from this file instead of asking}
                            {--dry-run : Show what would change without saving anything}';

    protected $description = 'Import a bills:export file, re-encrypting everything with this environment\'s app key';

    public function handle(): int
    {
        $identifier = $this->argument('user');
        $user = Str::isUuid($identifier) ? User::find($identifier) : User::where('email', $identifier)->first();
        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }
        $path = $this->argument('file');
        if (!is_file($path) || !is_readable($path)) {
            $this->error('The file does not exist or is not readable.');

            return self::FAILURE;
        }

        $passphrase = self::readPassphrase($this, confirm: false);
        if ($passphrase === null) {
            return self::FAILURE;
        }

        try {
            $payload = DataTransfer::open(file_get_contents($path), $passphrase);
            $counts = DataTransfer::importAtomically($user, $payload, (bool) $this->option('dry-run'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Counts only: the data is personal and shouldn't end up in terminal logs
        $this->info(($this->option('dry-run') ? 'Dry run, nothing saved. Would import:' : 'Imported:'));
        $this->table(['', 'created', 'updated', 'unchanged'], [
            ['bills', $counts['bills_created'], $counts['bills_updated'], $counts['bills_unchanged']],
            ['transactions', $counts['transactions_created'], $counts['transactions_updated'], $counts['transactions_unchanged']],
        ]);
        $this->line(sprintf('Bill links: %d added, %d removed.', $counts['links_added'], $counts['links_removed']));

        return self::SUCCESS;
    }

    /** From --passphrase-file, then BILLS_TRANSFER_PASSPHRASE, then a hidden prompt. Never from a command-line argument. */
    public static function readPassphrase(Command $command, bool $confirm): ?string
    {
        $file = $command->option('passphrase-file');
        if ($file) {
            if (!is_file($file) || !is_readable($file)) {
                $command->error('The passphrase file does not exist or is not readable.');

                return null;
            }

            return rtrim((string) file_get_contents($file), "\r\n");
        }
        $env = getenv('BILLS_TRANSFER_PASSPHRASE');
        if ($env !== false && $env !== '') {
            return $env;
        }
        $passphrase = $command->secret('Passphrase (at least 12 characters)');
        if ($confirm && $passphrase !== $command->secret('Repeat the passphrase')) {
            $command->error('The passphrases do not match.');

            return null;
        }

        return $passphrase ?: null;
    }
}
