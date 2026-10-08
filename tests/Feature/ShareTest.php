<?php

namespace Tests\Feature;

use App\Models\BankTransaction;
use App\Models\Bill;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_the_validity_line_and_theme_toggle_are_hidden_when_printing(): void
    {
        $owner = $this->makeUser();
        $page = $this->get('/share/'.$this->link($owner, ['expires_at' => now()->addDays(3)])->token)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<button[^>]*id="theme-toggle"[^>]*d-print-none#', $page);
        $this->assertMatchesRegularExpression('#<div class="[^"]*d-print-none[^"]*">Link érvényes eddig#u', $page);
        // The page prints in the light theme and goes back to the chosen one afterwards
        $this->assertStringContainsString("'beforeprint'", $page);
        $this->assertStringContainsString("'afterprint'", $page);

        $gone = $this->get('/share/'.str_repeat('z', 48))->getContent();
        $this->assertMatchesRegularExpression('#<button[^>]*id="theme-toggle"[^>]*d-print-none#', $gone);
    }

    public function test_the_repeat_payment_table_shows_invoice_number_and_type_in_one_column(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'REPEAT/9']);
        $this->pay($owner, $bill, '2025-02-05', 14673);
        $this->pay($owner, $bill, '2025-03-05', 14673);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        // One header cell for both, with the type under the invoice number, and no cell of its own
        $this->assertStringContainsString('Számla sorszáma / Típus', $page);
        $this->assertMatchesRegularExpression('#<td>REPEAT/9<div class="small text-body-secondary">Fűtés \+ melegvíz</div></td>#u', $page);
        $this->assertStringNotContainsString('<th>Típus</th>', $page);
        $header = substr($page, strpos($page, '<thead>'), strpos($page, '</thead>') - strpos($page, '<thead>'));
        $this->assertSame(5, preg_match_all('/<th[ >]/', $header), 'invoice/type, period, amount, payments, extra');
    }

    public function test_amount_status_and_payments_share_one_column_with_a_status_badge(): void
    {
        $owner = $this->makeUser();
        $paid = $this->bill($owner, ['invoice_number' => 'PAID/1', 'period_start' => '2025-01-01', 'period_end' => '2025-01-31']);
        $this->pay($owner, $paid, '2025-02-05', 14673);
        $this->pay($owner, $paid, '2025-03-05', 14673);
        $this->bill($owner, ['invoice_number' => 'FUTURE/1', 'period_start' => '2025-02-01', 'period_end' => '2025-02-28', 'due_date' => '2099-01-01', 'amount' => 5000]);
        $this->bill($owner, ['invoice_number' => 'LATE/1', 'period_start' => '2025-03-01', 'period_end' => '2025-03-31', 'due_date' => '2020-01-01', 'amount' => 6000]);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $table = substr($page, strpos($page, '<h3 class="h5 mt-3">Fűtés + melegvíz</h3>'));

        // One combined header, no separate amount or status cell
        $this->assertStringContainsString('<th>Összeg és kifizetés</th>', $table);
        $this->assertStringNotContainsString('<th>Állapot', $table);
        $header = substr($table, 0, strpos($table, '</thead>'));
        $this->assertSame(4, preg_match_all('/<th[ >]/', $header), 'invoice, period, due date, amount and payment');
        $this->assertStringNotContainsString('class="text-end text-nowrap">'.$this->money(14644), $table);

        // The amount leads the cell, then the Bootstrap badges, then the payments
        $this->assertMatchesRegularExpression('#<span class="fw-semibold me-1">'.preg_quote($this->money(14644), '#').'</span>\s*<span class="badge text-bg-success">Kifizetve</span>\s*<span class="badge text-bg-danger ms-1">2 alkalommal kifizetve</span>\s*<div>2025\. 02\. 05\.#u', $table);
        $this->assertMatchesRegularExpression('#'.preg_quote($this->money(5000), '#').'</span>\s*<span class="badge text-bg-warning">Kifizetetlen</span>#u', $table);
        $this->assertMatchesRegularExpression('#'.preg_quote($this->money(6000), '#').'</span>\s*<span class="badge text-bg-danger">Lejárt</span>#u', $table);
    }

    public function test_the_theme_toggle_is_a_plain_link_style_button(): void
    {
        $owner = $this->makeUser();
        foreach ([$this->get('/share/'.$this->link($owner)->token), $this->get('/share/'.str_repeat('q', 48))] as $response) {
            $page = $response->getContent();
            $this->assertMatchesRegularExpression('#<button[^>]*class="btn btn-link [^"]*"[^>]*id="theme-toggle"|<button[^>]*id="theme-toggle"[^>]*class="btn btn-link #', $page);
            $this->assertStringNotContainsString('btn-outline', $page);
        }
    }

    /** The "breakdown by transfer" part of the page, or an empty string when there is none. */
    private function transfersSection(string $page): string
    {
        $start = strpos($page, 'Átutalások részletezése');

        return $start === false ? '' : substr($page, $start);
    }

    public function test_a_transfer_wholly_covered_by_its_bills_is_broken_down(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'COVERED/1', 'amount' => 14644, 'period_start' => '2024-04-01', 'period_end' => '2024-04-30']);
        $this->pay($owner, $bill, '2024-07-05', 14673, ['note' => 'PRIVATE-NOTE']);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $section = $this->transfersSection($page);

        $this->assertStringContainsString('Átutalások részletezése', $section);
        // The date, then the bold amount on its own line under it, in a single column
        $this->assertStringContainsString('<th>Átutalás dátuma és összege</th>', $section);
        $this->assertMatchesRegularExpression('#<div>2024\. 07\. 05\.</div>\s*<div class="fw-bold">'.preg_quote($this->money(14673), '#').'</div>#u', $section);
        $this->assertStringNotContainsString('<th>Átutalás</th>', $section);
        $this->assertMatchesRegularExpression('#COVERED/1 <span class="text-body-secondary">· Fűtés \+ melegvíz, 2024\. 04\. 01\. – 2024\. 04\. 30\.</span> · '.preg_quote($this->money(14644), '#').'#u', $section);
        // The invoices' sum closes the invoices cell: plain, left-aligned, with no rule above it
        $this->assertMatchesRegularExpression('#<div class="mt-1"><span class="small text-body-secondary">Számlák összesen</span> <span class="fw-semibold">'.preg_quote($this->money(14644), '#').'</span></div>#u', $section);
        $this->assertStringNotContainsString('border-top', $section);
        $this->assertStringNotContainsString('text-end text-nowrap fw-semibold', $section);
        $this->assertStringNotContainsString('PRIVATE-NOTE', $page);
    }

    public function test_only_transfers_that_bills_cover_in_full_are_listed(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'OK/1', 'amount' => 10000]), '2025-01-10', 10029);
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'ROUND/1', 'amount' => 10000]), '2025-01-11', 9850);
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'TOOSMALL/1', 'amount' => 10000]), '2025-01-12', 9790);
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'PARTIAL/1', 'amount' => 10000]), '2025-01-13', 54460);
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'FLAGGED/1', 'amount' => 10000]), '2025-01-14', 10029, ['accounted' => true]);
        $this->pay($owner, $this->bill($owner, ['invoice_number' => 'AMOUNT/1', 'amount' => 10000]), '2025-01-15', 60000, ['accounted_amount' => 50000]);
        BankTransaction::create(['user_id' => $owner->id, 'date' => '2025-01-16', 'amount' => 403880, 'note' => 'RENT']);

        $section = $this->transfersSection($this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent());

        foreach (['OK/1', 'ROUND/1'] as $listed) {
            $this->assertStringContainsString($listed, $section, "$listed should be listed");
        }
        foreach (['TOOSMALL/1', 'PARTIAL/1', 'FLAGGED/1', 'AMOUNT/1'] as $left) {
            $this->assertStringNotContainsString($left, $section, "$left should not be listed");
        }
        $this->assertStringNotContainsString($this->money(403880), $section);
        $this->assertStringNotContainsString($this->money(54460), $section);
        $this->assertStringNotContainsString($this->money(60000), $section);
    }

    public function test_transfers_in_a_group_are_one_entry(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'SPLIT/1', 'amount' => 50630]);
        $first = $this->pay($owner, $bill, '2024-09-08', 41759);
        $second = BankTransaction::create(['user_id' => $owner->id, 'date' => '2024-09-08', 'amount' => 8972]);
        $group = (string) Str::uuid();
        BankTransaction::whereIn('id', [$first->id, $second->id])->update(['group_id' => $group]);

        $section = $this->transfersSection($this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent());

        $this->assertSame(1, substr_count($section, 'SPLIT/1'));
        // Two transfers on the same day: the date once, then both amounts in bold under it
        $this->assertSame(1, substr_count($section, '2024. 09. 08.'));
        $this->assertMatchesRegularExpression('#<div>2024\. 09\. 08\.</div>\s*<div class="fw-bold">'.preg_quote($this->money(8972), '#').'</div>\s*<div class="fw-bold">'.preg_quote($this->money(41759), '#').'</div>#u', $section);
        $this->assertSame(1, substr_count($section, 'Számlák összesen'));
        $this->assertStringContainsString('2 átutalásra bontva (összesen '.$this->money(50731).')', $section);
    }

    public function test_a_bill_paid_twice_appears_under_each_transfer_in_date_order(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'TWICE/1', 'amount' => 14644]);
        $this->pay($owner, $bill, '2024-07-05', 14673);
        $this->pay($owner, $bill, '2024-06-06', 14673);

        $section = $this->transfersSection($this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent());

        $this->assertSame(2, substr_count($section, 'TWICE/1'));
        $this->assertLessThan(strpos($section, '2024. 07. 05.'), strpos($section, '2024. 06. 06.'));
    }

    public function test_the_section_is_left_out_when_nothing_qualifies(): void
    {
        $owner = $this->makeUser();
        $this->bill($owner);
        BankTransaction::create(['user_id' => $owner->id, 'date' => '2025-01-16', 'amount' => 403880]);

        $this->get('/share/'.$this->link($owner)->token)->assertOk()->assertDontSee('Átutalások részletezése');
        $this->get('/share/'.$this->link($owner)->token.'?lang=en')->assertOk()->assertDontSee('Breakdown by transfer');
    }

    public function test_the_breakdown_follows_the_language_option(): void
    {
        $owner = $this->makeUser();
        $this->pay($owner, $this->bill($owner), '2025-02-05', 14673);

        $this->get('/share/'.$this->link($owner)->token.'?lang=en')->assertOk()
            ->assertSee('Breakdown by transfer')->assertSee('Transfer date and amount')->assertSee('Paid invoices')->assertSee('Invoices total');
    }

    public function test_instalments_on_different_days_list_each_date_once_with_its_own_amounts(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'INSTALL/1', 'amount' => 52742]);
        $a = $this->pay($owner, $bill, '2025-04-29', 44277);
        $b = BankTransaction::create(['user_id' => $owner->id, 'date' => '2025-05-13', 'amount' => 8649]);
        $c = BankTransaction::create(['user_id' => $owner->id, 'date' => '2025-05-13', 'amount' => 100]);
        BankTransaction::whereIn('id', [$a->id, $b->id, $c->id])->update(['group_id' => (string) Str::uuid()]);

        $section = $this->transfersSection($this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent());

        $this->assertSame(1, substr_count($section, '2025. 04. 29.'));
        $this->assertSame(1, substr_count($section, '2025. 05. 13.'));
        $this->assertMatchesRegularExpression('#<div>2025\. 04\. 29\.</div>\s*<div class="fw-bold">'.preg_quote($this->money(44277), '#').'</div>\s*<div class="mt-1">2025\. 05\. 13\.</div>\s*<div class="fw-bold">'.preg_quote($this->money(100), '#').'</div>\s*<div class="fw-bold">'.preg_quote($this->money(8649), '#').'</div>#u', $section);
        $this->assertStringContainsString('3 átutalásra bontva (összesen '.$this->money(53026).')', $section);
    }

    public function test_a_credit_makes_a_smaller_transfer_wholly_cover_its_bill(): void
    {
        $owner = $this->makeUser();
        $credited = $this->bill($owner, ['invoice_number' => 'CREDIT/1', 'amount' => 32186, 'credit_applied' => 23567, 'type' => 'electricity']);
        $this->pay($owner, $credited, '2025-05-13', 8649);
        $plain = $this->bill($owner, ['invoice_number' => 'NOCREDIT/1', 'amount' => 32186, 'type' => 'electricity', 'period_start' => '2025-06-01', 'period_end' => '2025-06-30']);
        $this->pay($owner, $plain, '2025-07-13', 8649);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $section = $this->transfersSection($page);

        // With the credit the transfer pays what remained; without it the same transfer is nowhere near covering the bill
        $this->assertStringContainsString('CREDIT/1', $section);
        $this->assertStringNotContainsString('NOCREDIT/1', $section);
        $this->assertStringContainsString('(ebből jóváírás: '.$this->money(23567).')', $section);
        $this->assertStringContainsString('Számlák összesen jóváírás után</span> <span class="fw-semibold">'.$this->money(8619), $section);
        // The invoice's own row mentions the credit
        $this->assertStringContainsString('Ebből jóváírás: '.$this->money(23567).' (korábbi túlfizetésből)', $page);
    }

    public function test_an_overpayment_settled_by_credit_is_no_longer_listed_as_paid_more_than_once(): void
    {
        $owner = $this->makeUser();
        $paidTwice = $this->bill($owner, ['invoice_number' => 'TWICE/1', 'amount' => 23567, 'type' => 'electricity']);
        $this->pay($owner, $paidTwice, '2025-04-10', 23649);
        $this->pay($owner, $paidTwice, '2025-04-29', 23649);
        $next = $this->bill($owner, ['invoice_number' => 'NEXT/1', 'amount' => 32186, 'credit_applied' => 23567, 'credit_source_id' => $paidTwice->id,
            'type' => 'electricity', 'period_start' => '2025-05-01', 'period_end' => '2025-05-31']);

        $report = \App\Util\ShareReport::build($owner, now()->toDateString());
        $this->assertSame([], $report['multiple']);
        $this->assertSame(0, $report['extra_paid']);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $this->assertStringNotContainsString('többször lett kifizetve', $page);
        // Still visible where it belongs: on both invoices
        $this->assertStringContainsString('Túlfizetés jóváírva: '.$this->money(23567).' (NEXT/1)', $page);
        $this->assertStringContainsString('Jóváírás: '.$this->money(23567).' (a(z) TWICE/1 számla túlfizetéséből)', $page);
        // The badge stays, but muted and saying why, instead of the red "paid twice" warning
        $this->assertStringContainsString('<span class="badge text-bg-secondary ms-1">2 alkalommal kifizetve, jóváírással rendezve</span>', $page);
        $this->assertStringNotContainsString('<span class="badge text-bg-danger ms-1">2 alkalommal kifizetve</span>', $page);
    }

    public function test_a_partly_credited_overpayment_stays_listed_with_what_is_left(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'PART/1', 'amount' => 14644]);
        foreach (['2025-02-05', '2025-03-05', '2025-04-05'] as $day) {
            $this->pay($owner, $bill, $day, 14673);
        }
        $this->bill($owner, ['invoice_number' => 'NEXT/2', 'amount' => 20000, 'credit_applied' => 10000, 'credit_source_id' => $bill->id,
            'period_start' => '2025-05-01', 'period_end' => '2025-05-31']);

        $report = \App\Util\ShareReport::build($owner, now()->toDateString());
        $this->assertSame(14644 * 2 - 10000, $report['extra_paid']);
        $this->assertSame(10000, $report['multiple'][0]['credited']);
        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $this->assertStringContainsString('ebből már jóváírva: '.$this->money(10000), $page);
        // Still something outstanding, so the red badge stays
        $this->assertStringContainsString('<span class="badge text-bg-danger ms-1">3 alkalommal kifizetve</span>', $page);
        $this->assertStringNotContainsString('jóváírással rendezve', $page);
    }

    public function test_a_credit_without_a_source_does_not_offset_any_overpayment(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['invoice_number' => 'REPEAT/5', 'amount' => 5000]);
        $this->pay($owner, $bill, '2025-02-05', 5010);
        $this->pay($owner, $bill, '2025-03-05', 5010);
        $this->bill($owner, ['invoice_number' => 'LOOSE/1', 'amount' => 9000, 'credit_applied' => 5000, 'period_start' => '2025-05-01', 'period_end' => '2025-05-31']);

        $this->assertSame(5000, \App\Util\ShareReport::build($owner, now()->toDateString())['extra_paid']);
    }

    public function test_without_any_credit_the_headline_keeps_its_original_wording(): void
    {
        $owner = $this->makeUser();
        $bill = $this->bill($owner, ['amount' => 14644]);
        $this->pay($owner, $bill, '2025-02-05', 14673);
        $this->pay($owner, $bill, '2025-03-05', 14673);

        $page = $this->get('/share/'.$this->link($owner)->token)->assertOk()->getContent();
        $this->assertStringContainsString('többletbefizetés összesen: '.$this->money(14644).')', $page);
        $this->assertStringNotContainsString('ebből már jóváírva', $page);
    }
}
