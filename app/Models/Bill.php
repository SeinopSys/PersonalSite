<?php

namespace App\Models;

use App\Casts\EncryptedBool;
use App\Casts\EncryptedDate;
use App\Casts\EncryptedInt;
use App\Util\BlindIndex;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * App\Models\Bill
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string|null $sha256
 * @property string|null $invoice_number
 * @property CarbonInterface $period_start
 * @property CarbonInterface $period_end
 * @property CarbonInterface|null $due_date
 * @property int $amount
 * @property CarbonInterface|null $file_modified_at
 * @property bool $advance
 * @property int|null $credit_applied Part of the amount settled by credit from an earlier overpayment
 * @property string|null $credit_source_id The overpaid invoice that credit came from
 * @property-read User $user
 * @property-read Collection|BankTransaction[] $transactions
 * @method static Builder|Bill whereUserId($value)
 * @method static Builder|Bill newModelQuery()
 * @method static Builder|Bill newQuery()
 * @method static Builder|Bill query()
 * @mixin \Eloquent
 */
class Bill extends Model
{
    use Uuids;

    public const TYPES = ['sewage', 'heating', 'electricity', 'water'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'type', 'sha256', 'invoice_number', 'period_start', 'period_end', 'due_date', 'amount', 'file_modified_at', 'advance', 'credit_applied', 'credit_source_id',
    ];

    // All bill details are encrypted at rest with the app key
    protected $casts = [
        'type' => 'encrypted',
        'sha256' => 'encrypted',
        'invoice_number' => 'encrypted',
        'period_start' => EncryptedDate::class,
        'period_end' => EncryptedDate::class,
        'due_date' => EncryptedDate::class,
        'file_modified_at' => EncryptedDate::class,
        'advance' => EncryptedBool::class,
        'credit_applied' => EncryptedInt::class,
        'amount' => EncryptedInt::class,
    ];

    protected static function booted(): void
    {
        // Keyed hashes let duplicates be found without decrypting every row
        static::saving(function (Bill $bill) {
            $bill->sha256_index = BlindIndex::make($bill->sha256);
            $bill->invoice_number_index = BlindIndex::make($bill->invoice_number);
        });
    }

    /** What had to be paid by transfer: the amount less any credit carried over from an earlier overpayment. */
    public function payableAmount(): int
    {
        return $this->amount - ($this->credit_applied ?? 0);
    }

    public function creditSource(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'credit_source_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(BankTransaction::class, 'bill_bank_transaction');
    }
}
