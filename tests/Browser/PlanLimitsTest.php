<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Enums\TenantRestriction;
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

    visitPage('organization/employees', '/admin/acme/employees', $dark, $mobile)->assertSee('Tu plan cubre 50 personas y ya llegaste al tope. Pide una ampliación a Humetría.');
    visitPage('tenancy/work-centers', '/admin/acme/work-centers', $dark, $mobile)->assertSee('Tu plan cubre 3 centros de trabajo y ya llegaste al tope.');
})->with($looks);

it('turns off the buttons that add once the plan is full, and links the mail that asks for more when an address is set', function (): void {
    config(['app.support_email' => 'soporte@humetria.mx']);
    acmeOnAPlan(50, 50, 3, 3);

    visitPage('organization/employees', '/admin/acme/employees')
        ->assertSee('Tu plan cubre 50 personas y ya llegaste al tope.')
        ->assertPresent('button[disabled]:has-text("Dar de alta")')
        ->assertPresent('a[href="mailto:soporte@humetria.mx?subject=Ampliar%20personas%20de%20'.rawurlencode(Tenant::query()->where('slug', 'acme')->sole()->trade_name).'"]');
    visitPage('tenancy/work-centers', '/admin/acme/work-centers')
        ->assertPresent('button[disabled]:has-text("Dar de alta centro")')
        // A tile that invites to add, and then does nothing, is noise at the limit: only the header's button, off, and the notice.
        ->assertDontSee('Agrega otro centro')
        ->assertPresent('a[href^="mailto:soporte@humetria.mx?subject=Ampliar%20centros%20de%20"]');
});

it('leaves the buttons on and shows no mail link while there is room and no address is set', function (): void {
    config(['app.support_email' => null]);
    acmeOnAPlan(50, 10, 3, 1);

    visitPage('organization/employees', '/admin/acme/employees')
        ->assertMissing('button[disabled]:has-text("Dar de alta")')
        ->assertMissing('a[href^="mailto:"]');
});

it('names no mail link at the limit when no address is set, only whom to ask', function (): void {
    config(['app.support_email' => null]);
    acmeOnAPlan(50, 50, 3, 3);

    visitPage('organization/employees', '/admin/acme/employees')
        ->assertSee('Pide una ampliación a Humetría.')
        ->assertMissing('a[href^="mailto:"]');
});

it('shows a read-only company no button that adds anything, in centers, employees and evaluations', function (): void {
    signInToPanel();
    Tenant::query()->where('slug', 'acme')->sole()->update(['restriction' => TenantRestriction::ReadOnly, 'restriction_reason' => 'Solo lectura', 'restricted_at' => now()]);

    visitPage('tenancy/work-centers', '/admin/acme/work-centers')
        ->assertSee('Tu empresa está en solo lectura.')
        ->assertDontSee('Dar de alta centro')
        ->assertDontSee('Agrega otro centro');
    visitPage('organization/employees', '/admin/acme/employees')
        ->assertDontSee('Cargar CSV')
        ->assertMissing('button:has-text("Dar de alta")');
    visitPage('campaigns/index', '/admin/acme/campaigns')
        ->assertDontSee('Nueva evaluación');
});
