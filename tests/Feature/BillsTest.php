<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'testuser', string $role = 'user'): User
    {
        return User::create([
            'name' => $name,
            'email' => "$name@example.com",
            'password' => bcrypt('password'),
            'lang' => 'en',
            'role' => $role,
        ]);
    }

    private function billPayload(array $override = []): array
    {
        return $override + [
            'type' => 'water',
            'sha256' => str_repeat('a', 64),
            'invoice_number' => 'ABC/12345678',
            'period_start' => '2025-01-01',
            'period_end' => '2025-01-31',
            'due_date' => '2025-02-15',
            'amount' => 12345,
        ];
    }

    public function test_requires_login_and_non_banned_role(): void
    {
        $this->get('/bills/data')->assertRedirect('/login');
        $this->actingAs($this->makeUser('banned', 'ban'))->getJson('/bills/data')->assertForbidden();
        $this->actingAs($this->makeUser('plain', 'user'))->getJson('/bills/data')->assertOk();
    }

    public function test_details_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [$this->billPayload(['amount' => 424242, 'invoice_number' => 'SECRET/99999999'])]])->assertOk();
        $this->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 424242, 'note' => 'private note'])->assertOk();

        $bill = (array) \DB::table('bills')->first();
        $tx = (array) \DB::table('bank_transactions')->first();
        foreach (['type', 'sha256', 'invoice_number', 'period_start', 'period_end', 'due_date', 'amount'] as $column) {
            $this->assertStringStartsWith('eyJ', $bill[$column], "bills.$column is not encrypted");
        }
        foreach (['date', 'amount', 'note'] as $column) {
            $this->assertStringStartsWith('eyJ', $tx[$column], "bank_transactions.$column is not encrypted");
        }
        $this->assertStringNotContainsString('SECRET', json_encode($bill));
        $this->assertNotSame(str_repeat('a', 64), $bill['sha256_index']);

        $this->assertSame(424242, Bill::first()->amount);
        $this->assertSame('2025-01-31', Bill::first()->period_end->toDateString());
        $this->assertSame('private note', BankTransaction::first()->note);
    }

    public function test_store_and_list_bills_with_negative_amount(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->postJson('/bills', ['bills' => [$this->billPayload(['amount' => -99])]])
            ->assertOk()->assertJsonPath('created.0.amount', -99);

        $this->getJson('/bills/data')->assertOk()->assertJsonCount(1, 'bills');
    }

    public function test_duplicate_hash_and_invoice_number_are_reported(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->postJson('/bills', ['bills' => [$this->billPayload()]])->assertOk();

        $byHash = $this->postJson('/bills', ['bills' => [$this->billPayload(['invoice_number' => 'OTHER/1'])]])->assertOk();
        $byHash->assertJsonPath('duplicates.0.matched', 'sha256')->assertJsonCount(0, 'created');

        $byInvoice = $this->postJson('/bills', ['bills' => [$this->billPayload(['sha256' => str_repeat('b', 64)])]])->assertOk();
        $byInvoice->assertJsonPath('duplicates.0.matched', 'invoice_number');

        $this->assertSame(1, Bill::count());
    }

    public function test_users_cannot_touch_each_others_bills(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $id = $this->actingAs($owner)->postJson('/bills', ['bills' => [$this->billPayload()]])->json('created.0.id');

        $this->actingAs($other)->deleteJson("/bills/$id")->assertStatus(500)->assertJsonPath('status', false);
        $this->assertSame(1, Bill::count());
        $this->getJson('/bills/data')->assertJsonCount(0, 'bills');
    }

    public function test_transaction_requires_date_and_amount(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bank-transactions', ['amount' => 100])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->postJson('/bank-transactions', ['date' => '2025-02-01'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_one_transaction_can_pay_multiple_bills(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $a = $this->postJson('/bills', ['bills' => [$this->billPayload()]])->json('created.0.id');
        $b = $this->postJson('/bills', ['bills' => [$this->billPayload([
            'type' => 'sewage', 'sha256' => str_repeat('c', 64), 'invoice_number' => 'X/2', 'amount' => 500,
        ])]])->json('created.0.id');

        $tx = $this->postJson('/bank-transactions', [
            'date' => '2025-02-10', 'amount' => 12845, 'bill_ids' => [$a, $b],
        ])->assertOk()->json('transaction.id');

        $data = $this->getJson('/bills/data')->json();
        foreach ($data['bills'] as $bill) {
            $this->assertSame([$tx], $bill['transaction_ids']);
        }

        $this->deleteJson("/bank-transactions/$tx")->assertOk();
        foreach ($this->getJson('/bills/data')->json('bills') as $bill) {
            $this->assertSame([], $bill['transaction_ids']);
        }
    }

    public function test_cannot_link_foreign_bill(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $id = $this->actingAs($owner)->postJson('/bills', ['bills' => [$this->billPayload()]])->json('created.0.id');

        $this->actingAs($other)->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 1, 'bill_ids' => [$id]])
            ->assertStatus(500)->assertJsonPath('status', false);
    }

    public function test_data_includes_gap_analysis(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [
            $this->billPayload(),
            $this->billPayload(['sha256' => str_repeat('d', 64), 'invoice_number' => 'Z/3', 'period_start' => '2025-03-01', 'period_end' => '2025-03-31']),
        ]])->assertOk();

        $this->getJson('/bills/data')->assertJsonPath('analysis.water.gaps.0.from', '2025-02-01');
    }

    public function test_csv_import_is_idempotent_and_scoped_to_user(): void
    {
        $user = $this->makeUser();
        $csv = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($csv, "Banki azonosító,Tranzakció időpontja,Összeg,Forgalom típusa,Devizanem\n"
            ."2,2026-10-08 12:09:00,- 118,ESETI MEGBÍZÁSOK KÖLTSÉGE,HUF\n"
            ."1,2026-10-08 12:09:22,-33 847,AZONNALI FIZETÉS,HUF\n");

        $this->artisan('bills:import-transactions', ['user' => $user->email, 'file' => $csv, '--dry-run' => true])
            ->expectsOutput('Would create 1 transaction(s); 0 already imported.')->assertSuccessful();
        $this->assertSame(0, BankTransaction::count());

        $this->artisan('bills:import-transactions', ['user' => $user->email, 'file' => $csv])
            ->expectsOutput('Created 1 transaction(s); 0 already imported.')->assertSuccessful();
        $this->artisan('bills:import-transactions', ['user' => $user->email, 'file' => $csv])
            ->expectsOutput('Created 0 transaction(s); 1 already imported.')->assertSuccessful();

        $tx = BankTransaction::first();
        $this->assertSame(33965, $tx->amount);
        $this->assertNull($tx->note);
        $this->assertSame($user->id, $tx->user_id);
        $this->assertStringStartsWith('eyJ', \DB::table('bank_transactions')->value('external_id'));
        unlink($csv);
    }

    public function test_csv_import_skips_transactions_above_the_threshold(): void
    {
        $user = $this->makeUser();
        $csv = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($csv, "Banki azonosító,Tranzakció időpontja,Összeg,Forgalom típusa,Devizanem\n"
            ."1,2026-10-08 12:09:22,-33 847,AZONNALI FIZETÉS,HUF\n"
            ."2,2026-09-23 04:35:00,-403 880,NAPKÖZBENI ÁTUTALÁS,HUF\n"
            ."3,2026-09-01 04:35:00,-100 000,AZONNALI FIZETÉS,HUF\n");

        $this->artisan('bills:import-transactions', ['user' => $user->email, 'file' => $csv])
            ->expectsOutput('Created 2 transaction(s); 0 already imported.')
            ->expectsOutput('Skipped 1 transaction(s) above 100000 Ft')->assertSuccessful();
        $this->assertEqualsCanonicalizing([33847, 100000], BankTransaction::all()->pluck('amount')->all());

        $this->artisan('bills:import-transactions', ['user' => $user->email, 'file' => $csv, '--max-amount' => 0])
            ->expectsOutput('Created 1 transaction(s); 2 already imported.')->assertSuccessful();
        unlink($csv);
    }

    public function test_reupload_fills_in_missing_file_date_without_duplicating(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [$this->billPayload()]])->assertOk()->assertJsonPath('created.0.file_modified_at', null);

        $again = $this->postJson('/bills', ['bills' => [$this->billPayload(['file_modified_at' => '2025-02-10'])]])->assertOk();
        $again->assertJsonCount(0, 'created')->assertJsonPath('duplicates.0.backfilled', true);
        $this->assertSame(1, Bill::count());
        $this->assertSame('2025-02-10', Bill::first()->file_modified_at->toDateString());

        // A second re-upload must not overwrite the stored date
        $this->postJson('/bills', ['bills' => [$this->billPayload(['file_modified_at' => '2025-03-01'])]])
            ->assertOk()->assertJsonPath('duplicates.0.backfilled', false);
        $this->assertSame('2025-02-10', Bill::first()->file_modified_at->toDateString());
    }

    public function test_entries_without_hash_or_invoice_number_are_caught_by_type_period_and_amount(): void
    {
        $this->actingAs($this->makeUser());
        $manual = $this->billPayload(['sha256' => null, 'invoice_number' => null]);
        $this->postJson('/bills', ['bills' => [$manual]])->assertOk()->assertJsonCount(1, 'created');

        $this->postJson('/bills', ['bills' => [$manual]])->assertOk()
            ->assertJsonCount(0, 'created')->assertJsonPath('duplicates.0.matched', 'period_amount');
        $this->postJson('/bills', ['bills' => [$manual + [], array_merge($manual, ['amount' => 1])]])->assertOk()->assertJsonCount(1, 'created');
        $this->assertSame(2, Bill::count());
    }

    public function test_update_keeps_file_date(): void
    {
        $this->actingAs($this->makeUser());
        $id = $this->postJson('/bills', ['bills' => [$this->billPayload(['file_modified_at' => '2025-02-10'])]])->json('created.0.id');

        $this->putJson("/bills/$id", $this->billPayload(['amount' => 777, 'file_modified_at' => '2025-02-10']))->assertOk()
            ->assertJsonPath('bill.file_modified_at', '2025-02-10')->assertJsonPath('bill.amount', 777);
    }

    public function test_transaction_can_be_marked_as_accounted_for_and_it_stays_marked(): void
    {
        $this->actingAs($this->makeUser());
        $id = $this->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 5000, 'accounted' => true])
            ->assertOk()->assertJsonPath('transaction.accounted', true)->json('transaction.id');
        $this->assertStringStartsWith('eyJ', \DB::table('bank_transactions')->value('accounted'));

        // An update that doesn't mention the flag leaves it alone
        $this->putJson("/bank-transactions/$id", ['date' => '2025-02-11', 'amount' => 5000])->assertOk()
            ->assertJsonPath('transaction.accounted', true);
        $this->putJson("/bank-transactions/$id", ['date' => '2025-02-11', 'amount' => 5000, 'accounted' => false])->assertOk()
            ->assertJsonPath('transaction.accounted', false);
        $this->assertFalse(BankTransaction::first()->accounted);
    }

    public function test_transactions_default_to_not_accounted_for(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 5000])
            ->assertOk()->assertJsonPath('transaction.accounted', false);
    }

    public function test_accounted_amount_is_stored_encrypted_kept_on_update_and_clearable(): void
    {
        $this->actingAs($this->makeUser());
        $id = $this->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 60000, 'accounted_amount' => 40000])
            ->assertOk()->assertJsonPath('transaction.accounted_amount', 40000)->json('transaction.id');
        $this->assertStringStartsWith('eyJ', \DB::table('bank_transactions')->value('accounted_amount'));

        $this->putJson("/bank-transactions/$id", ['date' => '2025-02-10', 'amount' => 60000])->assertOk()
            ->assertJsonPath('transaction.accounted_amount', 40000);
        $this->putJson("/bank-transactions/$id", ['date' => '2025-02-10', 'amount' => 60000, 'accounted_amount' => null])->assertOk()
            ->assertJsonPath('transaction.accounted_amount', null);
        $this->postJson('/bank-transactions', ['date' => '2025-02-10', 'amount' => 1, 'accounted_amount' => -5])
            ->assertUnprocessable()->assertJsonValidationErrors('accounted_amount');
    }

    private function makeTransactions(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->postJson('/bank-transactions', ['date' => '2025-02-1'.$i, 'amount' => 1000 + $i])->json('transaction.id');
        }

        return $ids;
    }

    public function test_transactions_can_be_grouped_merged_and_ungrouped(): void
    {
        $this->actingAs($this->makeUser());
        [$a, $b, $c, $d] = $this->makeTransactions(4);

        $this->postJson('/bank-transactions/group', ['transaction_ids' => [$a, $b]])->assertOk();
        $groups = BankTransaction::pluck('group_id', 'id');
        $this->assertNotNull($groups[$a]);
        $this->assertSame($groups[$a], $groups[$b]);
        $this->assertNull($groups[$c]);

        // Grouping one member with another transaction pulls the whole group together
        $this->postJson('/bank-transactions/group', ['transaction_ids' => [$b, $c]])->assertOk();
        $groups = BankTransaction::pluck('group_id', 'id');
        $this->assertSame($groups[$a], $groups[$c]);
        $this->assertNull($groups[$d]);

        // Removing one of three leaves a group of two; removing another dissolves it
        $this->postJson('/bank-transactions/ungroup', ['transaction_ids' => [$c]])->assertOk();
        $this->assertSame(2, BankTransaction::whereNotNull('group_id')->count());
        $this->postJson('/bank-transactions/ungroup', ['transaction_ids' => [$a]])->assertOk();
        $this->assertSame(0, BankTransaction::whereNotNull('group_id')->count());
    }

    public function test_deleting_a_member_dissolves_a_group_of_two_and_group_json_is_exposed(): void
    {
        $this->actingAs($this->makeUser());
        [$a, $b] = $this->makeTransactions(2);
        $this->postJson('/bank-transactions/group', ['transaction_ids' => [$a, $b]])->assertOk();
        $this->getJson('/bills/data')->assertJsonPath('transactions.0.group_id', BankTransaction::first()->group_id);

        $this->deleteJson("/bank-transactions/$a")->assertOk();
        $this->assertNull(BankTransaction::first()->group_id);
    }

    public function test_cannot_group_other_users_transactions_or_a_single_one(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAs($owner);
        [$a, $b] = $this->makeTransactions(2);

        $this->actingAs($this->makeUser('other'))->postJson('/bank-transactions/group', ['transaction_ids' => [$a, $b]])
            ->assertStatus(500)->assertJsonPath('status', false);
        $this->actingAs($owner)->postJson('/bank-transactions/group', ['transaction_ids' => [$a]])->assertUnprocessable();
        $this->assertSame(0, BankTransaction::whereNotNull('group_id')->count());
    }

    public function test_advance_flag_is_stored_encrypted_defaults_false_and_is_filled_in_on_reupload(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [$this->billPayload()]])->assertOk()->assertJsonPath('created.0.advance', false);
        $this->assertSame(false, Bill::first()->advance);

        // The same file read again, now recognised as an advance invoice, updates the stored bill
        $this->postJson('/bills', ['bills' => [$this->billPayload(['advance' => true])]])->assertOk()->assertJsonCount(0, 'created');
        $this->assertTrue(Bill::first()->advance);
        $this->assertStringStartsWith('eyJ', \DB::table('bills')->value('advance'));
    }

    public function test_updating_a_bill_keeps_the_advance_flag_unless_sent(): void
    {
        $this->actingAs($this->makeUser());
        $id = $this->postJson('/bills', ['bills' => [$this->billPayload(['advance' => true])]])->json('created.0.id');
        $this->putJson("/bills/$id", $this->billPayload(['amount' => 99]))->assertOk()->assertJsonPath('bill.advance', true);
        $this->putJson("/bills/$id", $this->billPayload(['amount' => 99, 'advance' => false]))->assertOk()->assertJsonPath('bill.advance', false);
    }

    public function test_period_analysis_in_the_data_endpoint_ignores_advances_nested_in_a_settlement(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [
            $this->billPayload(['sha256' => str_repeat('1', 64), 'invoice_number' => 'A1', 'period_start' => '2025-01-01', 'period_end' => '2025-02-01', 'advance' => true]),
            $this->billPayload(['sha256' => str_repeat('2', 64), 'invoice_number' => 'S1', 'period_start' => '2025-01-01', 'period_end' => '2025-04-01', 'amount' => 777]),
        ]])->assertOk();
        $this->getJson('/bills/data')->assertJsonCount(0, 'analysis.water.overlaps');
    }

    public function test_credit_applied_is_stored_encrypted_defaults_to_zero_and_can_be_cleared(): void
    {
        $this->actingAs($this->makeUser());
        $id = $this->postJson('/bills', ['bills' => [$this->billPayload(['amount' => 32186, 'credit_applied' => 23567])]])
            ->assertOk()->assertJsonPath('created.0.credit_applied', 23567)->json('created.0.id');
        $this->assertStringStartsWith('eyJ', \DB::table('bills')->value('credit_applied'));
        $this->assertSame(8619, Bill::first()->payableAmount());

        // Not sent: kept. Sent as null: cleared.
        $this->putJson("/bills/$id", $this->billPayload(['amount' => 32186]))->assertOk()->assertJsonPath('bill.credit_applied', 23567);
        $this->putJson("/bills/$id", $this->billPayload(['amount' => 32186, 'credit_applied' => null]))->assertOk()->assertJsonPath('bill.credit_applied', 0);
        $this->assertSame(32186, Bill::first()->payableAmount());
    }

    public function test_a_credit_cannot_exceed_the_bill_but_negative_amount_bills_are_still_fine(): void
    {
        $this->actingAs($this->makeUser());
        $this->postJson('/bills', ['bills' => [$this->billPayload(['amount' => 1000, 'credit_applied' => 1001])]])->assertUnprocessable()->assertJsonValidationErrors('credit_applied');
        $this->postJson('/bills', ['bills' => [$this->billPayload(['amount' => 1000, 'credit_applied' => -1])]])->assertUnprocessable();
        $this->postJson('/bills', ['bills' => [$this->billPayload(['amount' => -99])]])->assertOk();
        $this->assertSame(1, Bill::count());
    }

    public function test_the_credit_source_must_be_another_of_your_own_bills_and_means_nothing_without_a_credit(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner);
        $source = $this->postJson('/bills', ['bills' => [$this->billPayload(['sha256' => str_repeat('1', 64), 'invoice_number' => 'SRC/1'])]])->json('created.0.id');
        $target = $this->postJson('/bills', ['bills' => [$this->billPayload(['sha256' => str_repeat('2', 64), 'invoice_number' => 'TGT/1', 'amount' => 30000])]])->json('created.0.id');

        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 5000, 'credit_source_id' => $source]))
            ->assertOk()->assertJsonPath('bill.credit_source_id', $source);
        // Kept when not sent, cleared with the credit
        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 5000]))->assertOk()->assertJsonPath('bill.credit_source_id', $source);
        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 0]))->assertOk()->assertJsonPath('bill.credit_source_id', null);

        // Not itself, not a stranger's bill, not a made-up id
        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 5000, 'credit_source_id' => $target]))->assertUnprocessable();
        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 5000, 'credit_source_id' => (string) \Illuminate\Support\Str::uuid()]))->assertUnprocessable();
        $stranger = $this->makeUser('stranger');
        $theirs = Bill::create(['user_id' => $stranger->id, 'type' => 'water', 'period_start' => '2025-01-01', 'period_end' => '2025-01-31', 'amount' => 100]);
        $this->putJson("/bills/$target", $this->billPayload(['amount' => 30000, 'credit_applied' => 5000, 'credit_source_id' => $theirs->id]))->assertUnprocessable();
    }
}
