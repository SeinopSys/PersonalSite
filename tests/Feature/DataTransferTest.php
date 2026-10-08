<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\User;
use App\Util\DataTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class DataTransferTest extends TestCase
{
    use RefreshDatabase;

    private const PASS = 'correct horse battery staple';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/transfer-'.Str::random(8);
        mkdir($this->dir);
        file_put_contents("{$this->dir}/pass", self::PASS."\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*") ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function makeUser(string $name): User
    {
        return User::create(['name' => $name, 'email' => "$name@example.com", 'password' => bcrypt('x'), 'lang' => 'en', 'role' => 'user']);
    }

    /** A small but complete data set: advance + settlement, a group, a repeat payment, hand-entered and imported rows. */
    private function seedData(User $u): void
    {
        $adv = Bill::create(['user_id' => $u->id, 'type' => 'water', 'sha256' => str_repeat('a', 64), 'invoice_number' => 'FVV/1',
            'period_start' => '2024-09-05', 'period_end' => '2024-10-29', 'due_date' => '2024-11-15', 'amount' => 2659, 'file_modified_at' => '2024-11-01', 'advance' => true]);
        $set = Bill::create(['user_id' => $u->id, 'type' => 'water', 'sha256' => str_repeat('b', 64), 'invoice_number' => 'FVV/2',
            'period_start' => '2024-09-05', 'period_end' => '2025-03-04', 'amount' => 2880, 'credit_applied' => 1000, 'credit_source_id' => $adv->id]);
        $heat = Bill::create(['user_id' => $u->id, 'type' => 'heating', 'invoice_number' => 'H1', 'period_start' => '2024-04-01', 'period_end' => '2024-04-30', 'amount' => 14644]);

        $t1 = BankTransaction::create(['user_id' => $u->id, 'date' => '2024-06-06', 'amount' => 33214, 'external_id' => 'BANK-1']);
        $t2 = BankTransaction::create(['user_id' => $u->id, 'date' => '2024-07-05', 'amount' => 14673, 'note' => 'repeat', 'accounted_amount' => 5]);
        $t3 = BankTransaction::create(['user_id' => $u->id, 'date' => '2024-07-05', 'amount' => 9807, 'accounted' => true]);
        $t1->bills()->attach([$adv->id, $heat->id]);
        $t2->bills()->attach([$heat->id]);
        $t3->bills()->attach([$set->id]);
        $gid = (string) Str::uuid();
        BankTransaction::whereIn('id', [$t2->id, $t3->id])->update(['group_id' => $gid]);
    }

    private function normalise(array $payload): array
    {
        unset($payload['exported_at']);
        foreach (['bills', 'transactions'] as $k) {
            usort($payload[$k], fn ($a, $b) => $a['id'] <=> $b['id']);
        }
        foreach ($payload['transactions'] as &$t) {
            sort($t['bill_ids']);
        }

        return $payload;
    }

    private function export(User $u, ?string $name = 'out.enc'): string
    {
        $path = "{$this->dir}/$name";
        $this->artisan('bills:export', ['user' => $u->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])->assertSuccessful();

        return $path;
    }

    private function wipe(User $u): void
    {
        $u->bankTransactions()->delete();
        $u->bills()->delete();
    }

    public function test_round_trip_into_a_fresh_account_keeps_everything_including_links_and_groups(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $before = $this->normalise(DataTransfer::collect($local));
        $path = $this->export($local);

        // "Production": a different account with nothing in it (and, in real life, a different app key)
        $this->wipe($local);
        $prod = $this->makeUser('prod');
        $this->artisan('bills:import', ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])->assertSuccessful();

        $this->assertSame($before, $this->normalise(DataTransfer::collect($prod)));
        $this->assertSame(3, $prod->bills()->count());
        $this->assertSame(4, DB::table('bill_bank_transaction')->count());
        $this->assertSame(1, BankTransaction::whereNotNull('group_id')->distinct()->count('group_id'));
        $this->assertTrue(Bill::where('invoice_number_index', \App\Util\BlindIndex::make('FVV/1'))->first()->advance);
    }

    public function test_the_imported_rows_are_encrypted_at_rest_and_the_file_has_no_readable_data(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $file = file_get_contents($path);
        foreach (['FVV/1', 'BANK-1', '14644', 'water', 'repeat'] as $plain) {
            $this->assertStringNotContainsString($plain, $file);
        }
        $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));

        $this->wipe($local);
        $this->artisan('bills:import', ['user' => $this->makeUser('prod')->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])->assertSuccessful();
        $raw = (array) DB::table('bills')->where('invoice_number_index', \App\Util\BlindIndex::make('FVV/1'))->first();
        foreach (['type', 'invoice_number', 'amount', 'period_start', 'advance'] as $col) {
            $this->assertStringStartsWith('eyJ', $raw[$col], "$col not encrypted");
        }
    }

    public function test_importing_the_same_file_again_changes_nothing(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $this->wipe($local);
        $prod = $this->makeUser('prod');
        $args = ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"];
        $this->artisan('bills:import', $args)->assertSuccessful();
        $first = $this->normalise(DataTransfer::collect($prod));

        $payload = DataTransfer::open(file_get_contents($path), self::PASS);
        $counts = DataTransfer::importAtomically($prod, $payload, false);
        $this->assertSame(0, $counts['bills_created'] + $counts['bills_updated'] + $counts['transactions_created'] + $counts['transactions_updated']);
        $this->assertSame(3, $counts['bills_unchanged']);
        $this->assertSame(0, $counts['links_added'] + $counts['links_removed']);
        $this->assertSame($first, $this->normalise(DataTransfer::collect($prod)));
    }

    public function test_dry_run_saves_nothing(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $this->wipe($local);
        $prod = $this->makeUser('prod');

        $this->artisan('bills:import', ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass", '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Bill::count() + BankTransaction::count() + DB::table('bill_bank_transaction')->count());
    }

    public function test_wrong_passphrase_damaged_and_tampered_files_are_rejected(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $file = file_get_contents($this->export($local));

        foreach ([
            'wrong passphrase' => [$file, 'another passphrase entirely'],
            'garbage' => ['not json at all', self::PASS],
            'tampered data' => [str_replace('"data": "', '"data": "A', $file), self::PASS],
            'tampered header' => [preg_replace('/"iterations": \d+/', '"iterations": 100001', $file), self::PASS],
        ] as $label => [$contents, $pass]) {
            try {
                DataTransfer::open($contents, $pass);
                $this->fail("$label was accepted");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        // The untouched file still opens with the right passphrase
        $this->assertCount(3, DataTransfer::open($file, self::PASS)['bills']);
    }

    public function test_ids_that_belong_to_another_user_abort_the_import_without_changes(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $prod = $this->makeUser('prod');

        // The local rows were not removed, so their ids are taken by another account
        $this->artisan('bills:import', ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])
            ->expectsOutputToContain('already exists for another user')->assertFailed();
        $this->assertSame(0, $prod->bills()->count() + $prod->bankTransactions()->count());
    }

    public function test_data_already_on_the_target_is_merged_by_file_hash_invoice_number_and_bank_id(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $this->wipe($local);

        $prod = $this->makeUser('prod');
        // Already uploaded on prod with other ids, and with a wrong amount
        Bill::create(['user_id' => $prod->id, 'type' => 'water', 'sha256' => str_repeat('a', 64), 'invoice_number' => 'FVV/1',
            'period_start' => '2024-09-05', 'period_end' => '2024-10-29', 'amount' => 1]);
        BankTransaction::create(['user_id' => $prod->id, 'date' => '2024-06-06', 'amount' => 1, 'external_id' => 'BANK-1']);

        $this->artisan('bills:import', ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])->assertSuccessful();
        $this->assertSame(3, $prod->bills()->count());
        $this->assertSame(3, $prod->bankTransactions()->count());
        $this->assertSame(2659, $prod->bills()->get()->first(fn ($b) => $b->invoice_number === 'FVV/1')->amount);
        $this->assertSame(33214, $prod->bankTransactions()->get()->first(fn ($t) => $t->external_id === 'BANK-1')->amount);
        $this->assertSame(4, DB::table('bill_bank_transaction')->count());
    }

    public function test_export_needs_a_long_passphrase_and_never_overwrites(): void
    {
        $u = $this->makeUser('local');
        file_put_contents("{$this->dir}/short", 'short');
        $this->artisan('bills:export', ['user' => $u->email, 'file' => "{$this->dir}/x.enc", '--passphrase-file' => "{$this->dir}/short"])
            ->expectsOutputToContain('at least 12 characters')->assertFailed();
        $this->assertFileDoesNotExist("{$this->dir}/x.enc");

        $path = $this->export($u);
        $before = file_get_contents($path);
        $this->artisan('bills:export', ['user' => $u->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])
            ->expectsOutputToContain('refusing to overwrite')->assertFailed();
        $this->assertSame($before, file_get_contents($path));
    }

    public function test_the_passphrase_can_come_from_the_environment(): void
    {
        $u = $this->makeUser('local');
        putenv('BILLS_TRANSFER_PASSPHRASE='.self::PASS);
        try {
            $this->artisan('bills:export', ['user' => $u->email, 'file' => "{$this->dir}/env.enc"])->assertSuccessful();
            $this->assertIsArray(DataTransfer::open(file_get_contents("{$this->dir}/env.enc"), self::PASS));
        } finally {
            putenv('BILLS_TRANSFER_PASSPHRASE');
        }
    }

    public function test_a_credit_on_a_bill_survives_the_round_trip(): void
    {
        $local = $this->makeUser('local');
        $this->seedData($local);
        $path = $this->export($local);
        $this->wipe($local);
        $prod = $this->makeUser('prod');
        $this->artisan('bills:import', ['user' => $prod->email, 'file' => $path, '--passphrase-file' => "{$this->dir}/pass"])->assertSuccessful();

        $credited = $prod->bills()->get()->first(fn ($b) => $b->invoice_number === 'FVV/2');
        $this->assertSame(1000, $credited->credit_applied);
        $this->assertSame($prod->bills()->get()->first(fn ($b) => $b->invoice_number === 'FVV/1')->id, $credited->credit_source_id);
        $this->assertNull($prod->bills()->get()->first(fn ($b) => $b->invoice_number === 'H1')->credit_applied);
    }
}
