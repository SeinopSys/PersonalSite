<?php

declare(strict_types=1);

namespace App\Util;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moves a user's bills, bank transactions, links and groups between environments (e.g. local -> production).
 *
 * Stored data is encrypted with each environment's own APP_KEY, so an export has to be readable without it. The file
 * is therefore sealed with a passphrase (AES-256-GCM, key derived with PBKDF2-HMAC-SHA256) and can travel over scp.
 * Importing re-encrypts everything with the target's key. Ids are kept, so links and groups survive and importing the
 * same file again changes nothing. PDFs and share links are never included.
 */
final class DataTransfer
{
    public const VERSION = 1;

    private const KDF_ITERATIONS = 600000;

    /** @return array<string, mixed> */
    public static function collect(User $user): array
    {
        $bills = $user->bills()->get();
        $transactions = $user->bankTransactions()->with('bills:id')->get();

        return [
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'bills' => $bills->map(fn (Bill $b) => [
                'id' => $b->id,
                'type' => $b->type,
                'sha256' => $b->sha256,
                'invoice_number' => $b->invoice_number,
                'period_start' => $b->period_start->toDateString(),
                'period_end' => $b->period_end->toDateString(),
                'due_date' => $b->due_date?->toDateString(),
                'amount' => $b->amount,
                'file_modified_at' => $b->file_modified_at?->toDateString(),
                'advance' => $b->advance,
                'credit_applied' => $b->credit_applied,
                'credit_source_id' => $b->credit_source_id,
                'created_at' => $b->created_at?->toIso8601String(),
            ])->values()->all(),
            'transactions' => $transactions->map(fn (BankTransaction $t) => [
                'id' => $t->id,
                'date' => $t->date->toDateString(),
                'amount' => $t->amount,
                'note' => $t->note,
                'external_id' => $t->external_id,
                'accounted' => $t->accounted,
                'accounted_amount' => $t->accounted_amount,
                'group_id' => $t->group_id,
                'bill_ids' => $t->bills->pluck('id')->values()->all(),
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** Encrypts a payload with a passphrase and returns the file contents. */
    public static function seal(array $payload, string $passphrase): string
    {
        self::requirePassphrase($passphrase);
        $header = ['v' => self::VERSION, 'kdf' => 'pbkdf2-sha256', 'iterations' => self::KDF_ITERATIONS, 'cipher' => 'aes-256-gcm'];
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = hash_pbkdf2('sha256', $passphrase, $salt, self::KDF_ITERATIONS, 32, true);
        $data = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, json_encode($header));
        if ($data === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return json_encode($header + [
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($data),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    /** @throws InvalidArgumentException when the file is damaged, from a newer version, or the passphrase is wrong */
    public static function open(string $file, string $passphrase): array
    {
        $env = json_decode($file, true);
        if (!is_array($env) || ($env['v'] ?? null) !== self::VERSION || ($env['cipher'] ?? '') !== 'aes-256-gcm'
            || ($env['kdf'] ?? '') !== 'pbkdf2-sha256' || !is_int($env['iterations'] ?? null) || $env['iterations'] < 100000) {
            throw new InvalidArgumentException('This is not a supported export file.');
        }
        $header = ['v' => $env['v'], 'kdf' => $env['kdf'], 'iterations' => $env['iterations'], 'cipher' => $env['cipher']];
        $salt = base64_decode((string) ($env['salt'] ?? ''), true);
        $iv = base64_decode((string) ($env['iv'] ?? ''), true);
        $tag = base64_decode((string) ($env['tag'] ?? ''), true);
        $data = base64_decode((string) ($env['data'] ?? ''), true);
        if ($salt === false || $iv === false || $tag === false || $data === false) {
            throw new InvalidArgumentException('This is not a supported export file.');
        }

        $key = hash_pbkdf2('sha256', $passphrase, $salt, $env['iterations'], 32, true);
        $plain = openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, json_encode($header));
        if ($plain === false) {
            throw new InvalidArgumentException('Wrong passphrase, or the file was changed.');
        }
        $payload = json_decode($plain, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('The export is damaged.');
        }

        return $payload;
    }

    /**
     * Writes the payload into the target user's account and reports what happened. Records are matched by id first,
     * then by file hash / invoice number (bills) or the bank's own id (transactions), so data already entered on the
     * target is merged rather than duplicated. Run it inside a database transaction to be able to back out.
     *
     * @return array<string, int>
     */
    public static function import(User $target, array $payload): array
    {
        self::validate($payload);

        $counts = ['bills_created' => 0, 'bills_updated' => 0, 'bills_unchanged' => 0,
            'transactions_created' => 0, 'transactions_updated' => 0, 'transactions_unchanged' => 0,
            'links_added' => 0, 'links_removed' => 0];

        $billModels = [];
        foreach ($payload['bills'] as $row) {
            $bill = self::findOwned(Bill::class, $target, $row['id'])
                ?? self::byIndex(Bill::class, $target, 'sha256_index', $row['sha256'] ?? null)
                ?? self::byIndex(Bill::class, $target, 'invoice_number_index', $row['invoice_number'] ?? null)
                ?? new Bill;
            self::save($bill, $target, $row, [
                'type' => $row['type'], 'sha256' => $row['sha256'] ?? null, 'invoice_number' => $row['invoice_number'] ?? null,
                'period_start' => $row['period_start'], 'period_end' => $row['period_end'], 'due_date' => $row['due_date'] ?? null,
                'amount' => $row['amount'], 'file_modified_at' => $row['file_modified_at'] ?? null, 'advance' => (bool) ($row['advance'] ?? false),
                'credit_applied' => $row['credit_applied'] ?? null,
            ], 'bills', $counts);
            $billModels[$row['id']] = $bill;
        }

        // A credit points at the overpaid invoice by id; every bill exists now, so the ids can be mapped to the target's
        foreach ($payload['bills'] as $row) {
            $source = ($row['credit_source_id'] ?? null) !== null ? $billModels[$row['credit_source_id']]->id : null;
            $bill = $billModels[$row['id']];
            if ($bill->credit_source_id !== $source) {
                $bill->forceFill(['credit_source_id' => $source])->save();
            }
        }

        foreach ($payload['transactions'] as $row) {
            $tx = self::findOwned(BankTransaction::class, $target, $row['id'])
                ?? self::byIndex(BankTransaction::class, $target, 'external_id_index', $row['external_id'] ?? null)
                ?? new BankTransaction;
            self::save($tx, $target, $row, [
                'date' => $row['date'], 'amount' => $row['amount'], 'note' => $row['note'] ?? null,
                'external_id' => $row['external_id'] ?? null, 'accounted' => (bool) ($row['accounted'] ?? false),
                'accounted_amount' => $row['accounted_amount'] ?? null, 'group_id' => $row['group_id'] ?? null,
            ], 'transactions', $counts);

            $sync = $tx->bills()->sync(array_map(fn (string $id) => $billModels[$id]->id, $row['bill_ids'] ?? []));
            $counts['links_added'] += count($sync['attached']);
            $counts['links_removed'] += count($sync['detached']);
        }

        return $counts;
    }

    /** Imports inside a transaction; with $dryRun nothing is kept. */
    public static function importAtomically(User $target, array $payload, bool $dryRun): array
    {
        DB::beginTransaction();
        try {
            $counts = self::import($target, $payload);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        $dryRun ? DB::rollBack() : DB::commit();

        return $counts;
    }

    /**
     * Creates the record, or updates it only when something really differs. Values are compared as stored (decoded),
     * since assigning to an encrypted field always produces new ciphertext and would look like a change every time.
     */
    private static function save($model, User $target, array $row, array $attributes, string $name, array &$counts): void
    {
        if (!$model->exists) {
            $model->forceFill($attributes + ['id' => $row['id'], 'user_id' => $target->id]);
            if (!empty($row['created_at'])) {
                $model->created_at = $row['created_at'];
            }
            $model->save();
            $counts["{$name}_created"]++;

            return;
        }
        $differs = false;
        foreach ($attributes as $key => $value) {
            if (self::plain($model->{$key}) !== self::plain($value)) {
                $differs = true;
                break;
            }
        }
        if ($differs) {
            $model->forceFill($attributes)->save();
            $counts["{$name}_updated"]++;
        } else {
            $counts["{$name}_unchanged"]++;
        }
    }

    private static function plain(mixed $value): mixed
    {
        return $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : $value;
    }

    private static function findOwned(string $class, User $target, string $id)
    {
        $model = $class::find($id);
        if ($model === null) {
            return null;
        }
        if ($model->user_id !== $target->id) {
            throw new InvalidArgumentException("Record $id already exists for another user; nothing was imported.");
        }

        return $model;
    }

    private static function byIndex(string $class, User $target, string $column, ?string $value)
    {
        $index = BlindIndex::make($value);

        return $index === null ? null : $class::where('user_id', $target->id)->where($column, $index)->first();
    }

    private static function validate(array $payload): void
    {
        if (($payload['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('The export is from a different version of the tool.');
        }
        $validator = Validator::make($payload, [
            'bills' => 'present|array',
            'bills.*.id' => 'required|uuid|distinct',
            'bills.*.type' => 'required|in:'.implode(',', Bill::TYPES),
            'bills.*.sha256' => 'nullable|string|size:64',
            'bills.*.invoice_number' => 'nullable|string|max:64',
            'bills.*.period_start' => 'required|date_format:Y-m-d',
            'bills.*.period_end' => 'required|date_format:Y-m-d',
            'bills.*.due_date' => 'nullable|date_format:Y-m-d',
            'bills.*.amount' => 'required|integer',
            'bills.*.file_modified_at' => 'nullable|date_format:Y-m-d',
            'bills.*.advance' => 'nullable|boolean',
            'bills.*.credit_applied' => 'nullable|integer|min:0',
            'bills.*.credit_source_id' => 'nullable|uuid',
            'transactions' => 'present|array',
            'transactions.*.id' => 'required|uuid|distinct',
            'transactions.*.date' => 'required|date_format:Y-m-d',
            'transactions.*.amount' => 'required|integer',
            'transactions.*.note' => 'nullable|string|max:255',
            'transactions.*.external_id' => 'nullable|string|max:64',
            'transactions.*.accounted' => 'nullable|boolean',
            'transactions.*.accounted_amount' => 'nullable|integer|min:0',
            'transactions.*.group_id' => 'nullable|uuid',
            'transactions.*.bill_ids' => 'present|array',
            'transactions.*.bill_ids.*' => 'uuid',
        ]);
        if ($validator->fails()) {
            throw new InvalidArgumentException('The export is invalid: '.$validator->errors()->first());
        }
        $billIds = array_flip(array_column($payload['bills'], 'id'));
        foreach ($payload['bills'] as $row) {
            if (($row['credit_source_id'] ?? null) !== null && !isset($billIds[$row['credit_source_id']])) {
                throw new InvalidArgumentException('The export has a credit that points at a bill it does not contain.');
            }
        }
        foreach ($payload['transactions'] as $row) {
            foreach ($row['bill_ids'] as $id) {
                if (!isset($billIds[$id])) {
                    throw new InvalidArgumentException('The export links a transaction to a bill it does not contain.');
                }
            }
        }
    }

    private static function requirePassphrase(string $passphrase): void
    {
        if (mb_strlen($passphrase) < 12) {
            throw new InvalidArgumentException('Use a passphrase of at least 12 characters.');
        }
    }
}
