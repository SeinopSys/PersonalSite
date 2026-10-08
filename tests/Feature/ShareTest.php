<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'owner'): User
    {
        return User::create([
            'name' => $name, 'email' => "$name@example.com", 'password' => bcrypt('password'), 'lang' => 'en', 'role' => 'user',
        ]);
    }

    private function bill(User $user, array $o = []): Bill
    {
        static $n = 0;
        $n++;

        return Bill::create($o + [
            'user_id' => $user->id, 'type' => 'heating', 'sha256' => str_pad((string) $n, 64, 'a'), 'invoice_number' => "INV$n",
            'period_start' => '2025-01-01', 'period_end' => '2025-01-31', 'due_date' => '2099-01-01', 'amount' => 14644, 'advance' => false,
        ]);
    }

    private function pay(User $user, Bill $bill, string $date, int $amount, array $o = []): BankTransaction
    {
        $tx = BankTransaction::create($o + ['user_id' => $user->id, 'date' => $date, 'amount' => $amount]);
        $tx->bills()->attach($bill->id);

        return $tx;
    }

    private function link(User $user, array $o = []): ShareLink
    {
        return ShareLink::create($o + ['user_id' => $user->id, 'token' => ShareLink::makeToken(), 'view_count' => 0]);
    }

    private function money(int $n): string
    {
        return number_format($n, 0, ',', "\u{a0}")."\u{a0}Ft";
    }

    public function test_creating_a_link_requires_login_and_returns_a_long_random_url(): void
    {
        $this->postJson('/bills/shares')->assertUnauthorized();

        $user = $this->makeUser();
        $this->actingAs($user);
        $a = $this->postJson('/bills/shares', ['label' => 'Landlord'])->assertOk()->json('link');
        $b = $this->postJson('/bills/shares')->assertOk()->json('link');

        $this->assertMatchesRegularExpression('#/share/[A-Za-z0-9]{48}$#', $a['url']);
        $this->assertNotSame($a['url'], $b['url']);
        $this->assertSame('Landlord', $a['label']);
    }

    public function test_the_secret_and_label_are_stored_encrypted(): void
    {
        $this->actingAs($this->makeUser());
        $link = $this->postJson('/bills/shares', ['label' => 'Landlord'])->json('link');
        $token = basename($link['url']);

        $row = (array) \DB::table('share_links')->first();
        $this->assertStringStartsWith('eyJ', $row['token']);
        $this->assertStringStartsWith('eyJ', $row['label']);
        $this->assertStringNotContainsString($token, json_encode($row));
        $this->assertNotSame($token, $row['token_index']);
    }

    public function test_the_page_is_public_shows_invoices_and_counts_views(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'ABC/123']), '2025-02-05', 14673);
        $link = $this->link($owner);

        $this->get('/share/'.$link->token)->assertOk()->assertSee('ABC/123');
        $this->get('/share/'.$link->token);
        $link->refresh();
        $this->assertSame(2, $link->view_count);
        $this->assertNotNull($link->last_viewed_at);
    }

    public function test_the_page_is_not_indexable_cacheable_or_able_to_load_anything_else(): void
    {
        $owner = $this->makeUser();
        $response = $this->get('/share/'.$this->link($owner)->token)->assertOk();

        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('<script', $response->getContent());
    }

    public function test_bills_paid_more_than_once_are_marked_with_the_extra_amount(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'REPEAT/1', 'amount' => 14644]);
        $this->pay($owner, $bill, '2024-06-06', 33214);
        $this->pay($owner, $bill, '2024-07-05', 14673);
        $this->pay($owner, $bill, '2024-07-26', 33338);
        $link = $this->link($owner);

        $hu = $this->get('/share/'.$link->token)->assertOk()->assertSee('többször lett kifizetve')->assertSee('3 alkalommal kifizetve');
        $this->assertStringContainsString($this->money(14644 * 2), $hu->getContent());
        $this->assertStringContainsString('2024. 07. 05.', $hu->getContent());

        $this->get('/share/'.$link->token.'?lang=en')->assertOk()->assertSee('paid more than once')->assertSee('Paid 3 times');
    }

    public function test_missing_invoices_between_periods_are_marked(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner, ['period_start' => '2025-08-01', 'period_end' => '2025-08-31']), '2025-09-05', 15000);
        $this->pay($owner, $this->bill($owner, ['period_start' => '2025-10-01', 'period_end' => '2025-10-31']), '2025-11-05', 15000);

        $content = $this->get('/share/'.$this->link($owner)->token)->assertOk()->assertSee('Hiányzó számla')->getContent();
        $this->assertStringContainsString('2025. 09. 01. – 2025. 09. 30.', $content);
    }

    public function test_advance_invoices_inside_a_settlement_are_not_reported_as_overlaps(): void
    {
        $owner = $this->makeUser();
        $this->bill($owner, ['type' => 'water', 'period_start' => '2025-01-01', 'period_end' => '2025-02-01', 'advance' => true]);
        $this->bill($owner, ['type' => 'water', 'period_start' => '2025-01-01', 'period_end' => '2025-04-01']);

        $this->get('/share/'.$this->link($owner)->token.'?lang=en')->assertOk()
            ->assertDontSee('overlapping periods')->assertSee('Settlement: covers 1 advance invoice');
    }

    public function test_unrelated_data_is_not_shown(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner), '2025-02-05', 14673, ['note' => 'PRIVATE-NOTE', 'accounted_amount' => 111]);
        BankTransaction::create(['user_id' => $owner->id, 'date' => '2025-03-01', 'amount' => 403880, 'note' => 'RENT-NOTE']);
        $other = $this->makeUser('stranger');
        $this->bill($other, ['invoice_number' => 'STRANGER/999']);

        $content = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        foreach (['PRIVATE-NOTE', 'RENT-NOTE', 'STRANGER/999', '403', 'owner@example.com'] as $secret) {
            $this->assertStringNotContainsString($secret, $content, "leaked: $secret");
        }
    }

    public function test_unknown_malformed_expired_and_revoked_links_are_all_a_404(): void
    {
        $owner = $this->makeUser();
        $this->get('/share/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/share/short')->assertNotFound();

        $expired = $this->link($owner, ['expires_at' => now()->subDays(2)]);
        $this->get('/share/'.$expired->token)->assertNotFound();

        $today = $this->link($owner, ['expires_at' => now()]);
        $this->get('/share/'.$today->token)->assertOk();

        $revoked = $this->link($owner);
        $this->get('/share/'.$revoked->token)->assertOk();
        $this->actingAs($owner)->deleteJson('/bills/shares/'.$revoked->id)->assertOk();
        $this->get('/share/'.$revoked->token)->assertNotFound();
    }

    public function test_only_the_owner_can_list_or_revoke_a_link(): void
    {
        $owner = $this->makeUser();
        $link = $this->link($owner);
        $other = $this->makeUser('other');

        $this->actingAs($other)->getJson('/bills/shares')->assertOk()->assertJsonCount(0, 'links');
        $this->deleteJson('/bills/shares/'.$link->id)->assertStatus(500)->assertJsonPath('status', false);
        $this->assertSame(1, ShareLink::count());
        $this->actingAs($owner)->getJson('/bills/shares')->assertJsonCount(1, 'links');
    }

    public function test_expiry_can_be_chosen_when_creating_a_link(): void
    {
        $this->actingAs($this->makeUser());
        $link = $this->postJson('/bills/shares', ['expires_in_days' => 30])->assertOk()->json('link');
        $this->assertSame(now()->addDays(30)->toDateString(), $link['expires_at']);
        $this->postJson('/bills/shares', ['expires_in_days' => 5])->assertUnprocessable();
    }
}
