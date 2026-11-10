<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** The company at /admin/acme with a plan that covers `$seats` people and `$centers` work centers, `$usedSeats` and `$usedCenters` used. */
function acmeOnAPlan(int $seats, int $usedSeats, int $centers, int $usedCenters): void
{
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => CompanyLimits::SEATS, 'amount' => $seats, 'used' => $usedSeats]);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => CompanyLimits::CENTERS, 'amount' => $centers, 'used' => $usedCenters]);
}

it('shows how much of the plan the people and centers use, quietly while there is room', function (bool $dark, bool $mobile): void {
    acmeOnAPlan(50, 42, 3, 1);

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile)->assertSee('Usas 42 de 50 personas de tu plan.');
    visitPage('tenancy/work-centers', '/admin/acme/work-centers', $dark, $mobile)->assertSee('Usas 1 de 3 centros de trabajo de tu plan.');
})->with($looks);

it('asks for an expansion once the plan is full', function (bool $dark, bool $mobile): void {
    acmeOnAPlan(50, 50, 3, 3);

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile)->assertSee('Tu plan cubre 50 personas y ya llegaste al tope. Pide una ampliación a Humetria.');
    visitPage('tenancy/work-centers', '/admin/acme/work-centers', $dark, $mobile)->assertSee('Tu plan cubre 3 centros de trabajo y ya llegaste al tope.');
})->with($looks);
