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

    /** The one script allowed on the page, found through the nonce the policy announces. */
    private function assertOnlyTheThemeScript($response): void
    {
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match("/script-src 'nonce-([^']+)'/", $csp, $m), 'the policy must name a script nonce');
        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, '<script'), 'exactly one script is allowed');
        $this->assertStringContainsString('<script nonce="'.$m[1].'">', $content);
        $this->assertStringNotContainsString("script-src 'unsafe-inline'", $csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
    }

    public function test_the_page_is_not_indexable_cacheable_and_only_loads_its_own_stylesheet_and_one_script(): void
    {
        $owner = $this->makeUser();
        $link = $this->link($owner);
        $response = $this->get('/share/'.$link->token)->assertOk();

        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertOnlyTheThemeScript($response);

        $content = $response->getContent();
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/bootstrap\.css[^"]*">#', $content);
        $this->assertDoesNotMatchRegularExpression('#(?:src|href)="(?:https?:)?//#', $content);
        $this->assertStringContainsString('id="theme-toggle"', $content);
        $this->assertStringContainsString('prefers-color-scheme: dark', $content);

        // A fresh nonce for every request
        $nonce = fn ($r) => preg_match("/nonce-([^']+)'/", $r->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;
        $this->assertNotSame($nonce($response), $nonce($this->get('/share/'.$link->token)));
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

    public function test_every_unavailable_link_gets_the_same_friendly_page(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'SECRET/INV']), '2025-02-05', 14673);

        $revoked = $this->link($owner);
        $this->get('/share/'.$revoked->token)->assertOk();
        $this->actingAs($owner)->deleteJson('/bills/shares/'.$revoked->id)->assertOk();

        $pages = [
            'expired' => $this->get('/share/'.$this->link($owner, ['expires_at' => now()->subDays(2)])->token),
            'revoked' => $this->get('/share/'.$revoked->token),
            'never existed' => $this->get('/share/'.str_repeat('a', 48)),
            'too short' => $this->get('/share/short'),
            'too long' => $this->get('/share/'.str_repeat('b', 200)),
            'odd characters' => $this->get('/share/'.rawurlencode('<script>alert(1)</script>')),
            'extra path' => $this->get('/share/'.str_repeat('c', 48).'/more/path'),
            'no token' => $this->get('/share'),
            'empty token' => $this->get('/share/'),
        ];
        $first = null;
        foreach ($pages as $label => $response) {
            $response->assertNotFound()->assertSee('Ez a link már nem érhető el')->assertSee('Kérj új linket');
            $content = preg_replace('/nonce="[^"]+"/', 'nonce="N"', $response->getContent());
            $first ??= $content;
            $this->assertSame($first, $content, "$label looks different from the others");
            $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'), $label);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), $label);
            $this->assertOnlyTheThemeScript($response);
            $this->assertStringContainsString('id="theme-toggle"', $content, $label);
        }
        // Nothing of the owner's data, no scripts, and none of the site's navigation or links
        foreach (['SECRET/INV', '<a ', '<nav', 'owner@example.com'] as $absent) {
            $this->assertStringNotContainsString($absent, $first, "found: $absent");
        }
    }

    public function test_the_unavailable_page_follows_the_language_option_and_does_not_count_a_view(): void
    {
        $owner = $this->makeUser();
        $expired = $this->link($owner, ['expires_at' => now()->subDay()]);

        $this->get('/share/'.$expired->token.'?lang=en')->assertNotFound()
            ->assertSee('This link is no longer available')->assertSee('ask the person who sent it');
        $this->assertSame(0, $expired->fresh()->view_count);
    }

    public function test_a_link_works_through_its_last_day_and_stops_the_day_after(): void
    {
        $owner = $this->makeUser();
        $this->get('/share/'.$this->link($owner, ['expires_at' => now()])->token)->assertOk();
        $this->get('/share/'.$this->link($owner, ['expires_at' => now()->subDay()])->token)->assertNotFound();
    }

    public function test_the_page_states_how_long_the_link_is_valid(): void
    {
        $owner = $this->makeUser();
        $until = now()->addDays(10);
        $with = $this->get('/share/'.$this->link($owner, ['expires_at' => $until])->token)->assertOk()->assertSee('Link érvényes eddig:');
        $this->assertStringContainsString($until->format('Y. m. d.'), $with->getContent());

        $this->get('/share/'.$this->link($owner, ['expires_at' => $until])->token.'?lang=en')->assertSee('Link valid until');
        $this->get('/share/'.$this->link($owner)->token)->assertOk()->assertDontSee('Link érvényes eddig:');
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
