<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\User;
use App\Util\BillPeriods;
use App\Util\BlindIndex;
use App\Util\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillsController extends Controller
{
    private const RULES = [
        'type' => 'required|in:sewage,heating,electricity,water',
        'sha256' => 'nullable|string|size:64|regex:/^[0-9a-f]+$/',
        'invoice_number' => 'nullable|string|max:64',
        'period_start' => 'required|date_format:Y-m-d',
        'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start',
        'due_date' => 'nullable|date_format:Y-m-d',
        'file_modified_at' => 'nullable|date_format:Y-m-d',
        'advance' => 'sometimes|boolean',
        'amount' => 'required|integer|between:-2000000000,2000000000',
    ];

    public static function billJson(Bill $bill): array
    {
        return [
            'id' => $bill->id,
            'type' => $bill->type,
            'sha256' => $bill->sha256,
            'invoice_number' => $bill->invoice_number,
            'period_start' => $bill->period_start->toDateString(),
            'period_end' => $bill->period_end->toDateString(),
            'due_date' => $bill->due_date?->toDateString(),
            'file_modified_at' => $bill->file_modified_at?->toDateString(),
            'advance' => $bill->advance,
            'amount' => $bill->amount,
            'transaction_ids' => $bill->relationLoaded('transactions') ? $bill->transactions->pluck('id')->values() : [],
        ];
    }

    public function index()
    {
        return view('bills', ['title' => __('global.bills'), 'js' => ['bills'], 'css' => ['bills']]);
    }

    public function data()
    {
        /** @var User $user */
        $user = Auth::user();

        // Details are encrypted, so ordering happens here rather than in SQL
        $bills = $user->bills()->with('transactions:id')->get()->sortBy(fn (Bill $b) => $b->period_start->toDateString())->values();
        $transactions = $user->bankTransactions()->with('bills:id')->get()->sortByDesc(fn ($t) => $t->date->toDateString())->values();

        $analysis = [];
        foreach (Bill::TYPES as $type) {
            $periods = $bills->where('type', $type)->map(fn (Bill $b) => [
                'id' => $b->id,
                'start' => $b->period_start->toDateString(),
                'end' => $b->period_end->toDateString(),
                'advance' => $b->advance,
            ])->values()->all();
            $analysis[$type] = BillPeriods::analyze($periods, now()->toDateString());
        }

        return Response::Done([
            'bills' => $bills->map(fn (Bill $b) => self::billJson($b))->values(),
            'transactions' => $transactions->map(fn ($t) => BankTransactionsController::transactionJson($t))->values(),
            'analysis' => $analysis,
        ]);
    }

    /**
     * Saves a batch of bills. Items matching an existing hash or invoice number are skipped and reported back.
     */
    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $rules = ['bills' => 'required|array|min:1|max:200'];
        foreach (self::RULES as $field => $rule) {
            $rules["bills.*.$field"] = $rule;
        }
        $validated = $request->validate($rules);

        $created = [];
        $duplicates = [];
        DB::transaction(function () use ($user, $validated, &$created, &$duplicates) {
            foreach ($validated['bills'] as $index => $item) {
                $item['sha256'] = $item['sha256'] ?? null;
                $item['invoice_number'] = $item['invoice_number'] ?? null;
                $item['file_modified_at'] = $item['file_modified_at'] ?? null;
                $item['advance'] = $item['advance'] ?? false;
                $match = $this->findDuplicate($user, $item);
                if ($match !== null) {
                    // Re-uploading a bill that was saved before its file date was recorded fills the date in
                    $backfilled = $match['bill']->file_modified_at === null && $item['file_modified_at'] !== null;
                    if ($backfilled) {
                        $match['bill']->update(['file_modified_at' => $item['file_modified_at']]);
                    }
                    // Likewise an advance flag found when the same file is read again
                    if ($item['advance'] && !$match['bill']->advance) {
                        $match['bill']->update(['advance' => true]);
                    }
                    $duplicates[] = ['index' => $index, 'matched' => $match['signal'], 'backfilled' => $backfilled];
                    continue;
                }
                $created[] = Bill::create($item + ['user_id' => $user->id]);
            }
        });

        return Response::Done([
            'created' => array_map(fn (Bill $b) => self::billJson($b->load('transactions:id')), $created),
            'duplicates' => $duplicates,
        ]);
    }

    public function update(Request $request, string $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $bill = $user->bills()->where('id', $id)->first();
        if ($bill === null) {
            return Response::Fail(__('bills.bill-not-found'));
        }

        $validated = $request->validate(self::RULES);
        $validated['sha256'] = $validated['sha256'] ?? null;
        $validated['invoice_number'] = $validated['invoice_number'] ?? null;
        $match = $this->findDuplicate($user, $validated, $bill->id);
        if ($match !== null) {
            return Response::Fail(__('bills.duplicate-'.$match['signal']));
        }

        // Left alone when the client doesn't send it
        $validated['advance'] = $validated['advance'] ?? $bill->advance;
        $bill->update($validated);

        return Response::Done(['bill' => self::billJson($bill->load('transactions:id'))]);
    }

    public function destroy(string $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $bill = $user->bills()->where('id', $id)->first();
        if ($bill === null) {
            return Response::Fail(__('bills.bill-not-found'));
        }
        $bill->delete();

        return Response::Done();
    }

    /**
     * Looks for an existing bill of the user that this one repeats: same file, same invoice number, or (for entries
     * without either) the same type, period and amount. Period and amount are encrypted, so that last check runs in PHP.
     *
     * @return array{signal: 'sha256'|'invoice_number'|'period_amount', bill: Bill}|null
     */
    private function findDuplicate(User $user, array $item, ?string $exceptId = null): ?array
    {
        foreach (['sha256', 'invoice_number'] as $column) {
            $index = BlindIndex::make($item[$column] ?? null);
            if ($index === null) {
                continue;
            }
            $query = Bill::where('user_id', $user->id)->where($column.'_index', $index);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }
            $bill = $query->first();
            if ($bill !== null) {
                return ['signal' => $column, 'bill' => $bill];
            }
        }

        $query = Bill::where('user_id', $user->id);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }
        $bill = $query->get()->first(fn (Bill $b) => $b->type === $item['type']
            && $b->period_start->toDateString() === $item['period_start']
            && $b->period_end->toDateString() === $item['period_end']
            && $b->amount === (int) $item['amount']);

        return $bill === null ? null : ['signal' => 'period_amount', 'bill' => $bill];
    }
}
