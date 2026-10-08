<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Util\DataTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExportBillsData extends Command
{
    protected $signature = 'bills:export
                            {user : Email or ID of the user whose data to export}
                            {file : Where to write the passphrase-protected export}
                            {--passphrase-file= : Read the passphrase from this file instead of asking}';

    protected $description = 'Export bills, bank transactions, links and groups to a passphrase-protected file for bills:import';

    public function handle(): int
    {
        $identifier = $this->argument('user');
        $user = Str::isUuid($identifier) ? User::find($identifier) : User::where('email', $identifier)->first();
        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $passphrase = ImportBillsData::readPassphrase($this, confirm: true);
        if ($passphrase === null) {
            return self::FAILURE;
        }

        $payload = DataTransfer::collect($user);
        try {
            $file = DataTransfer::seal($payload, $passphrase);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = $this->argument('file');
        if (file_exists($path)) {
            $this->error('That file already exists; refusing to overwrite it.');

            return self::FAILURE;
        }
        // Owner-only from the start, so the file is never readable by others, even briefly
        $handle = fopen($path, 'xb');
        if ($handle === false) {
            $this->error('Could not create the file.');

            return self::FAILURE;
        }
        chmod($path, 0600);
        fwrite($handle, $file);
        fclose($handle);

        $this->info(sprintf('Exported %d bills and %d transactions to %s (encrypted with your passphrase).',
            count($payload['bills']), count($payload['transactions']), $path));

        return self::SUCCESS;
    }
}
