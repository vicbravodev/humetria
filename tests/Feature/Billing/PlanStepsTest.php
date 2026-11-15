<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\PlanSteps;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Support\SubscriptionSteps;
use App\Modules\Tenancy\Models\Tenant;

mutates(SubscriptionSteps::class);

it('shows the price of the next band to a channel company that is about to cross it: 50 to 51 people', function (): void {
    [, $account] = paidCompany('consultant');
    $tenant = planFor($account, ['seats' => 50]);

    expect(resolve(PlanSteps::class)->describe($tenant->id, 'seats', 51))->toBe('Con 51 personas el importe pasa de $2,490 a $3,990 al mes, sin IVA.');
});

it('says nothing while the next size costs the same', function (): void {
    [, $account] = paidCompany('consultant');
    $tenant = planFor($account, ['seats' => 50]);

    expect(resolve(PlanSteps::class)->describe($tenant->id, 'seats', 50))->toBeNull();
});

it('says the next size needs a quotation when the table ends', function (): void {
    [, $account] = paidCompany();
    $tenant = planFor($account, ['plan' => Plan::Cumplimiento, 'profile' => Profile::Direct, 'seats' => 100]);

    expect(resolve(PlanSteps::class)->describe($tenant->id, 'seats', 101))->toBe('Con 101 personas tu plan requiere una cotización.');
});

it('shows what an extra work center costs', function (): void {
    [, $account] = paidCompany();
    $tenant = planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::Direct, 'seats' => 100, 'centers' => 3]);

    expect(resolve(PlanSteps::class)->describe($tenant->id, 'centers', 4))->toBe('Con 4 centros de trabajo el importe pasa de $9,900 a $10,049 al mes, sin IVA.');
});

it('says nothing for a company without a plan or whose price was negotiated', function (): void {
    [$unplanned] = paidCompany();
    [, $account] = paidCompany();
    $negotiated = planFor($account, ['plan' => Plan::Cumplimiento, 'profile' => Profile::Direct, 'seats' => 100, 'negotiated_price_cents' => 500_000, 'negotiated_valid_until' => null]);

    expect(resolve(PlanSteps::class)->describe($unplanned->id, 'seats', 200))->toBeNull()
        ->and(resolve(PlanSteps::class)->describe(Tenant::factory()->create()->id, 'seats', 200))->toBeNull()
        ->and(resolve(PlanSteps::class)->describe($negotiated->id, 'seats', 200))->toBeNull();
});
