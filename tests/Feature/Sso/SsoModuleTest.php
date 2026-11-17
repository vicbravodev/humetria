<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\SetSubscription;
use App\Modules\Billing\Data\SubscriptionData;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Support\Catalog;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ModuleStates;
use Carbon\CarbonImmutable;

function ssoEnabledFor(Tenant $tenant): bool
{
    resolve(ModuleStates::class)->forget();

    return resolve(CompanyModules::class)->enabled($tenant->id, 'sso');
}

it('includes single sign-on in the Enterprise plan and in no other', function (): void {
    $catalog = Catalog::version('v1');

    expect($catalog->modules(Plan::Enterprise))->toContain('sso')
        ->and($catalog->modules(Plan::Integral))->not->toContain('sso')
        ->and($catalog->modules(Plan::Cumplimiento))->not->toContain('sso')
        ->and($catalog->modules(Plan::Jornada))->not->toContain('sso');
});

it('gives single sign-on only to a company on the Enterprise plan', function (Plan $plan, bool $expected): void {
    $this->travelTo('2026-12-10 12:00:00');
    [$tenant] = paidCompany();

    resolve(SetSubscription::class)->handle($tenant->id, new SubscriptionData($plan, Profile::Direct, SubscriptionStatus::Active, 50, 1, CarbonImmutable::parse('2026-12-01')));

    expect(ssoEnabledFor($tenant))->toBe($expected);
})->with([
    'Enterprise' => [Plan::Enterprise, true],
    'Integral' => [Plan::Integral, false],
    'Cumplimiento' => [Plan::Cumplimiento, false],
]);

it('does not hand single sign-on to a company that has no plan at all', function (): void {
    [$tenant] = paidCompany();

    expect(ssoEnabledFor($tenant))->toBeFalse();
});
