<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Models\User;
use App\Util\BlindIndex;
use App\Util\TransactionCsvParser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportBankTransactions extends Command
{
    protected $signature = 'bills:import-transactions
                            {user : Email or ID of the user to import for}
                            {file : Path to the bank CSV export}
                            {--max-amount=100000 : Skip transactions above this amount in Ft (fee included), 0 disables the limit}
                            {--dry-run : Show what would be imported without saving}';

    protected $description = 'Create bank transactions from a bank CSV export, to be linked to bills in the UI';

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

        try {
            $result = TransactionCsvParser::parse(file_get_contents($path));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $maxAmount = (int) $this->option('max-amount');
        if ($maxAmount < 0) {
            $this->error('--max-amount must not be negative.');

            return self::FAILURE;
        }

        $existing = BankTransaction::where('user_id', $user->id)->whereNotNull('external_id_index')->pluck('external_id_index')->flip();
        $created = 0;
        $already = 0;
        $tooLarge = 0;
        foreach ($result['payments'] as $payment) {
            // Large transfers such as rent aren't utility bills
            if ($maxAmount > 0 && $payment['amount'] > $maxAmount) {
                $tooLarge++;
                continue;
            }
            if ($existing->has(BlindIndex::make($payment['external_id']))) {
                $already++;
                continue;
            }
            if (!$this->option('dry-run')) {
                BankTransaction::create([
                    'user_id' => $user->id,
                    'date' => $payment['date'],
                    'amount' => $payment['amount'],
                    'external_id' => $payment['external_id'],
                ]);
            }
            $created++;
        }

        // Counts only: the export holds account numbers and amounts that shouldn't end up in logs
        $this->info(($this->option('dry-run') ? 'Would create' : 'Created')." $created transaction(s); $already already imported.");
        if ($tooLarge > 0) {
            $this->line("Skipped $tooLarge transaction(s) above $maxAmount Ft");
        }
        foreach ($result['skipped'] as $reason => $count) {
            $this->line("Skipped $count row(s): $reason");
        }

        return self::SUCCESS;
    }
}
