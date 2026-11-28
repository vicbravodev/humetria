<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\CompanyPayer;
use App\Modules\Billing\Data\OverdueAccount;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;

/** An issued statement of the account for this many pesos, issued that many days ago. */
function contractStatement(BillingAccount $account, int $pesos, int $days): void
{
    Statement::factory()->issued()->create(['billing_account_id' => $account->id, 'subtotal_cents' => $pesos * 100, 'vat_cents' => 0, 'total_cents' => $pesos * 100, 'issued_at' => now()->subDays($days)]);
}

beforeEach(function (): void {
    $this->travelTo('2026-11-26 15:00:00');
});

it('tells another module what all the accounts owe past due, and which accounts, the longest overdue first', function (): void {
    $old = BillingAccount::factory()->create(['legal_name' => 'Textiles del Norte']);
    contractStatement($old, 3_000, 60);
    contractStatement($old, 1_000, 20);
    contractStatement($old, 500, 3); // not due yet
    $recent = BillingAccount::factory()->create(['legal_name' => 'Escuelas Delta']);
    contractStatement($recent, 2_000, 16);
    contractStatement(BillingAccount::factory()->create(), 9_000, 2);

    $receivables = resolve(Receivables::class);

    expect($receivables->overdueCents())->toBe(600_000)
        ->and($receivables->overdueAccounts())->toEqual([
            new OverdueAccount($old->id, 'Textiles del Norte', 400_000, 46),
            new OverdueAccount($recent->id, 'Escuelas Delta', 200_000, 2),
        ])
        ->and($receivables->overdueAccounts(1))->toHaveCount(1);
});

it('says nothing is past due when nothing is', function (): void {
    $receivables = resolve(Receivables::class);

    expect($receivables->overdueCents())->toBe(0)->and($receivables->overdueAccounts())->toBe([]);
});

it('counts the billed plans that renew within the days asked, never a trial, an ended one or one that is cancelling', function (): void {
    Subscription::factory()->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->create(['starts_on' => '2026-11-01', 'renews_on' => '2027-02-01']);
    Subscription::factory()->status(SubscriptionStatus::CancelScheduled)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);
    Subscription::factory()->status(SubscriptionStatus::Trial)->create(['starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    $receivables = resolve(Receivables::class);

    expect($receivables->renewingWithin(30))->toBe(1)->and($receivables->renewingWithin(90))->toBe(2);
});

it('tells another module which account pays a company now and what that account owes past due', function (): void {
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Delta']);
    $link = AccountCompany::factory()->create(['billing_account_id' => $account->id, 'starts_on' => '2026-01-01']);
    contractStatement($account, 3_000, 60);
    contractStatement($account, 500, 3); // not due yet
    contractStatement(BillingAccount::factory()->create(), 9_000, 60); // someone else's

    expect(resolve(Receivables::class)->payerOf($link->tenant_id))
        ->toEqual(new CompanyPayer($account->id, 'Consultores Delta', 'Consultor', 300_000, 46));
});

it('says a company owes nothing past due while its account is current, and has no payer without a current link', function (): void {
    $account = BillingAccount::factory()->create(['legal_name' => 'Textiles del Norte']);
    $link = AccountCompany::factory()->create(['billing_account_id' => $account->id, 'starts_on' => '2026-01-01']);
    contractStatement($account, 500, 3);
    $gone = AccountCompany::factory()->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-06-01']);

    $receivables = resolve(Receivables::class);

    expect($receivables->payerOf($link->tenant_id))->toEqual(new CompanyPayer($account->id, 'Textiles del Norte', 'Directa', 0, 0))
        ->and($receivables->payerOf($gone->tenant_id))->toBeNull();
});
