<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(SetSubscription::class);

/** @return list<array{string, int, int}> resource, used, amount */
function limitsOf(Tenant $tenant): array
{
    return array_map(fn ($row): array => [$row->resource, $row->used, $row->amount], resolve(CompanyLimits::class)->usage($tenant->id));
}

function planOf(Plan $plan, int $seats, int $centers, SubscriptionStatus $status = SubscriptionStatus::Active): SubscriptionData
{
    return new SubscriptionData($plan, Profile::Direct, $status, $seats, $centers, CarbonImmutable::parse('2026-12-01'));
}

it('leaves a company with an account but no plan without limits', function (): void {
    [$tenant] = paidCompany();

    expect(limitsOf($tenant))->toBe([]);
});

it('limits the company to the people and centers the plan contracts, counting from what it already has', function (): void {
    [$tenant] = paidCompany();
    Employee::factory()->count(3)->create(['tenant_id' => $tenant->id]);

    resolve(SetSubscription::class)->handle($tenant->id, planOf(Plan::Integral, 50, 4));

    expect(limitsOf($tenant))->toBe([['seats', 3, 50], ['centers', 3, 4]]);
});

it('always covers the centers the plan includes, even when fewer were contracted', function (): void {
    [$tenant] = paidCompany();

    resolve(SetSubscription::class)->handle($tenant->id, planOf(Plan::Cumplimiento, 20, 1));

    expect(limitsOf($tenant))->toBe([['seats', 0, 20], ['centers', 0, 2]]);
});

it('moves the limits when the plan changes, keeping the count, and does not touch another company', function (): void {
    [$tenant] = paidCompany();
    [$other] = paidCompany();
    $set = resolve(SetSubscription::class);
    $set->handle($tenant->id, planOf(Plan::Cumplimiento, 50, 2));
    $set->handle($other->id, planOf(Plan::Cumplimiento, 10, 2));
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    resolve(CompanyLimits::class)->reconcile();

    $set->handle($tenant->id, planOf(Plan::Cumplimiento, 100, 3));

    expect(limitsOf($tenant))->toBe([['seats', 2, 100], ['centers', 2, 3]])
        ->and(limitsOf($other))->toBe([['seats', 0, 10], ['centers', 0, 2]]);
});

it('limits a trial to what it contracts too', function (): void {
    [$tenant] = paidCompany();

    resolve(SetSubscription::class)->handle($tenant->id, planOf(Plan::Jornada, 15, 1, SubscriptionStatus::Trial));

    expect(limitsOf($tenant))->toBe([['seats', 0, 15], ['centers', 0, 1]]);
});
