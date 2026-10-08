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
 * App\Models\BankTransaction
 *
 * @property string $id
 * @property string $user_id
 * @property CarbonInterface $date
 * @property int $amount
 * @property string|null $note
 * @property string|null $external_id
 * @property bool $accounted
 * @property int|null $accounted_amount
 * @property string|null $group_id
 * @property-read User $user
 * @property-read Collection|Bill[] $bills
 * @method static Builder|BankTransaction whereUserId($value)
 * @method static Builder|BankTransaction newModelQuery()
 * @method static Builder|BankTransaction newQuery()
 * @method static Builder|BankTransaction query()
 * @mixin \Eloquent
 */
class BankTransaction extends Model
{
    use Uuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'date', 'amount', 'note', 'external_id', 'accounted', 'accounted_amount', 'group_id'];

    // All transaction details are encrypted at rest with the app key
    protected $casts = [
        'date' => EncryptedDate::class,
        'amount' => EncryptedInt::class,
        'note' => 'encrypted',
        'external_id' => 'encrypted',
        'accounted' => EncryptedBool::class,
        'accounted_amount' => EncryptedInt::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (BankTransaction $transaction) {
            $transaction->external_id_index = BlindIndex::make($transaction->external_id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class, 'bill_bank_transaction');
    }
}
