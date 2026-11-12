<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * RH's list of balances on Wednesday 4 November 2026: Ana has a period, Caro has no start date.
 */
function leaveBalancesPanel(): string
{
    test()->travelTo('2026-11-04 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Caro Luz', 'hired_on' => null]);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);

    return "{$url}/vacations/balances";
}

it('shows RH the days of everybody and what is missing to grant them', function (bool $dark, bool $mobile): void {
    visitPage('leave/balances', leaveBalancesPanel().'?tab=all', $dark, $mobile)
        ->assertSee('Saldos')
        ->assertSee('Ana Solís')
        ->assertSee('14 días')
        ->assertSee('Capturar la fecha')
        ->assertSee('15 mar 2027')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('opens on what asks for action and moves to everybody', function (): void {
    visitPage('leave/balances', leaveBalancesPanel())
        ->assertSee('Caro Luz')
        ->assertDontSee('Ana Solís')
        ->click('nav[aria-label="Qué revisar de los saldos"] a:has-text("Todos")')
        ->assertSee('Ana Solís')
        ->assertNoJavaScriptErrors();
});

it('sends the statement to the people RH ticks, from a bar that appears only then', function (): void {
    visitPage('leave/balances', leaveBalancesPanel().'?tab=all')
        ->assertDontSee('Quitar selección')
        ->click('input[type=checkbox][aria-label="Elegir a Ana Solís"], button[aria-label="Elegir a Ana Solís"]')
        ->assertSee('1 seleccionada')
        ->press('Enviar constancia para acuse')
        ->assertSee('enlace')
        ->assertNoJavaScriptErrors();
});
