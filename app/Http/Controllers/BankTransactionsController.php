<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\User;
use App\Util\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankTransactionsController extends Controller
{
    private const RULES = [
        'date' => 'required|date_format:Y-m-d',
        'amount' => 'required|integer|between:-2000000000,2000000000',
        'note' => 'nullable|string|max:255',
        'accounted' => 'sometimes|boolean',
        'accounted_amount' => 'sometimes|nullable|integer|min:0|max:2000000000',
        'bill_ids' => 'sometimes|array|max:100',
        'bill_ids.*' => 'uuid|distinct',
    ];

    public static function transactionJson(BankTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'date' => $transaction->date->toDateString(),
            'amount' => $transaction->amount,
            'note' => $transaction->note,
            'accounted' => $transaction->accounted,
            'accounted_amount' => $transaction->accounted_amount,
            'group_id' => $transaction->group_id,
            'bill_ids' => $transaction->relationLoaded('bills') ? $transaction->bills->pluck('id')->values() : [],
        ];
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validate(self::RULES);

        $billIds = $validated['bill_ids'] ?? [];
        if (!$this->ownsAllBills($user, $billIds)) {
            return Response::Fail(__('bills.bill-not-found'));
        }

        $transaction = DB::transaction(function () use ($user, $validated, $billIds) {
            $transaction = BankTransaction::create([
                'user_id' => $user->id,
                'date' => $validated['date'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                'accounted' => $validated['accounted'] ?? false,
                'accounted_amount' => $validated['accounted_amount'] ?? null,
            ]);
            $transaction->bills()->sync($billIds);

            return $transaction;
        });

        return Response::Done(['transaction' => self::transactionJson($transaction->load('bills:id'))]);
    }

    public function update(Request $request, string $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $transaction = $user->bankTransactions()->where('id', $id)->first();
        if ($transaction === null) {
            return Response::Fail(__('bills.transaction-not-found'));
        }
        $validated = $request->validate(self::RULES);

        $billIds = $validated['bill_ids'] ?? [];
        if (!$this->ownsAllBills($user, $billIds)) {
            return Response::Fail(__('bills.bill-not-found'));
        }

        DB::transaction(function () use ($transaction, $validated, $billIds) {
            $transaction->update([
                'date' => $validated['date'],
                'amount' => $validated['amount'],
                'note' => $validated['note'] ?? null,
                // Left alone when the client doesn't send it
                'accounted' => $validated['accounted'] ?? $transaction->accounted,
                // Sending null clears it; leaving it out keeps it
                'accounted_amount' => array_key_exists('accounted_amount', $validated) ? $validated['accounted_amount'] : $transaction->accounted_amount,
            ]);
            $transaction->bills()->sync($billIds);
        });

        return Response::Done(['transaction' => self::transactionJson($transaction->load('bills:id'))]);
    }

    public function destroy(string $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $transaction = $user->bankTransactions()->where('id', $id)->first();
        if ($transaction === null) {
            return Response::Fail(__('bills.transaction-not-found'));
        }
        $groupId = $transaction->group_id;
        $transaction->delete();
        $this->dissolveSmallGroups($user, $groupId === null ? [] : [$groupId]);

        return Response::Done();
    }

    /**
     * Puts the given transactions into one group. Anyone already grouped with them joins too, so groups merge
     * instead of being split apart.
     */
    public function group(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = $request->validate([
            'transaction_ids' => 'required|array|min:2|max:100',
            'transaction_ids.*' => 'uuid|distinct',
        ])['transaction_ids'];

        $selected = $user->bankTransactions()->whereIn('id', $ids)->get();
        if ($selected->count() !== count($ids)) {
            return Response::Fail(__('bills.transaction-not-found'));
        }

        $existing = $selected->pluck('group_id')->filter()->unique()->all();
        DB::transaction(function () use ($user, $ids, $existing) {
            $user->bankTransactions()->where(function ($query) use ($ids, $existing) {
                $query->whereIn('id', $ids)->orWhereIn('group_id', $existing);
            })->update(['group_id' => (string) Str::uuid()]);
        });

        return Response::Done();
    }

    /**
     * Takes the given transactions out of their groups. A group left with a single member is dissolved.
     */
    public function ungroup(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = $request->validate([
            'transaction_ids' => 'required|array|min:1|max:100',
            'transaction_ids.*' => 'uuid|distinct',
        ])['transaction_ids'];

        $selected = $user->bankTransactions()->whereIn('id', $ids)->get();
        if ($selected->count() !== count($ids)) {
            return Response::Fail(__('bills.transaction-not-found'));
        }

        $groups = $selected->pluck('group_id')->filter()->unique()->all();
        DB::transaction(function () use ($user, $ids, $groups) {
            $user->bankTransactions()->whereIn('id', $ids)->update(['group_id' => null]);
            $this->dissolveSmallGroups($user, $groups);
        });

        return Response::Done();
    }

    /**
     * A group of one is just a transaction, so it is cleared.
     *
     * @param  string[]  $groupIds
     */
    private function dissolveSmallGroups(User $user, array $groupIds): void
    {
        foreach ($groupIds as $groupId) {
            $members = $user->bankTransactions()->where('group_id', $groupId);
            if ($members->count() === 1) {
                $members->update(['group_id' => null]);
            }
        }
    }

    private function ownsAllBills(User $user, array $billIds): bool
    {
        return $user->bills()->whereIn('id', $billIds)->count() === count($billIds);
    }
}
