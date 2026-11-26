<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\PlatformRevenue;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\ContractedRevenue;
use App\Modules\Tenancy\Models\Tenant;

mutates(ContractedRevenue::class);

beforeEach(fn () => $this->travelTo('2026-11-25 18:00 UTC'));

it('tells each company of a page its plan, what it pays a month before VAT and when it renews, adding up to the platform total', function (): void {
    $integral = Subscription::factory()->plan(Plan::Integral)->create(['renews_on' => '2026-12-15']);
    $cumplimiento = Subscription::factory()->plan(Plan::Cumplimiento)->create(['renews_on' => '2027-01-01']);
    $revenue = app(PlatformRevenue::class);

    $rows = $revenue->byCompany([$integral->tenant_id, $cumplimiento->tenant_id]);

    expect($rows[$integral->tenant_id]->plan)->toBe('Integral')
        ->and($rows[$integral->tenant_id]->renewsOn->toDateString())->toBe('2026-12-15')
        ->and($rows[$cumplimiento->tenant_id]->plan)->toBe('Cumplimiento')
        ->and($rows[$integral->tenant_id]->monthlyCents + $rows[$cumplimiento->tenant_id]->monthlyCents)->toBe($revenue->monthlyContractedCents())
        ->and($rows[$integral->tenant_id]->monthlyCents)->toBeGreaterThan(0);
});

it('names the plan of a company in trial or past due without a monthly figure, since it does not pay now', function (SubscriptionStatus $status): void {
    $subscription = Subscription::factory()->status($status)->create();

    $row = app(PlatformRevenue::class)->byCompany([$subscription->tenant_id])[$subscription->tenant_id];

    expect($row->plan)->toBe('Cumplimiento')->and($row->monthlyCents)->toBeNull();
})->with([SubscriptionStatus::Trial, SubscriptionStatus::PastDue]);

it('leaves out a company without a plan, one whose plan ended, and the companies not asked for', function (): void {
    $ended = Subscription::factory()->status(SubscriptionStatus::Ended)->create();
    $other = Subscription::factory()->create();
    $none = Tenant::factory()->create();

    expect(app(PlatformRevenue::class)->byCompany([$ended->tenant_id, $none->id]))->toBe([])
        ->and(app(PlatformRevenue::class)->byCompany([]))->toBe([]);
    expect($other->tenant_id)->not->toBeEmpty();
});

it('reads a page of companies in the same number of queries however many it holds', function (): void {
    $few = Subscription::factory()->count(2)->create()->pluck('tenant_id')->all();
    $many = Subscription::factory()->count(12)->create()->pluck('tenant_id')->all();
    $count = function (array $ids): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(PlatformRevenue::class)->byCompany($ids);

        return count(DB::getQueryLog());
    };

    expect($count($many))->toBe($count($few));
});

/** @return array{subtotal_cents: int, vat_cents: int, total_cents: int} */
function billedAmounts(int $subtotal): array
{
    return ['subtotal_cents' => $subtotal, 'vat_cents' => intdiv($subtotal * 16, 100), 'total_cents' => $subtotal + intdiv($subtotal * 16, 100)];
}

it('adds up what was billed each of the last months before VAT, the oldest first, a month without statements in zero', function (): void {
    Statement::factory()->issued()->create(['period_start' => '2026-11-01', ...billedAmounts(300_000)]);
    Statement::factory()->issued()->create(['period_start' => '2026-11-01', ...billedAmounts(200_000)]);
    Statement::factory()->issued()->create(['period_start' => '2026-09-01', ...billedAmounts(100_000)]);
    // A draft was never billed, and a month out of the window is not counted.
    Statement::factory()->create(['period_start' => '2026-11-01', ...billedAmounts(999_000)]);
    Statement::factory()->issued()->create(['period_start' => '2026-08-01', ...billedAmounts(777_000)]);

    expect(app(PlatformRevenue::class)->billedByMonth(3))->toBe([
        ['month' => '2026-09', 'cents' => 100_000],
        ['month' => '2026-10', 'cents' => 0],
        ['month' => '2026-11', 'cents' => 500_000],
    ]);
});
