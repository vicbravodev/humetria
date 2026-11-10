<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;

/**
 * RH's panel on Wednesday 4 November 2026 at noon in Mexico City: Ana is out today, Beto's period closed and Caro's request
 * waits for her manager.
 */
function leaveOverviewPanel(): string
{
    test()->travelTo('2026-11-04 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2020-01-15']);
    $beto = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Beto Ruiz', 'hired_on' => '2020-01-15']);
    $caro = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Caro Luz', 'hired_on' => null]);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-06', 'working_days' => 4]);
    LeaveRequest::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $caro->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02', 'working_days' => 2]);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $beto->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);

    return $url;
}

it('shows RH who is out, what waits and the windows about to close', function (bool $dark, bool $mobile): void {
    $url = leaveOverviewPanel();

    visitPage('leave/index', "{$url}/vacations", $dark, $mobile)
        ->assertSee('Resumen')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Fuera hoy')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Por responder')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Días por disfrutar')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Siguen en el saldo: oponer la prescripción es decisión de la empresa.')
        ->assertSee('1 persona no tiene fecha de ingreso.')
        ->assertSeeIn('section[aria-labelledby="leave-detail"]', 'Ana Solís')
        ->assertSeeIn('section[aria-labelledby="leave-detail"]', 'Vacaciones')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->click('nav[aria-label="Listas del resumen"] a:has-text("Ventanas por cerrar")')
        ->assertSeeIn('section[aria-labelledby="leave-detail"]', 'Beto Ruiz')
        ->assertSeeIn('section[aria-labelledby="leave-detail"]', 'Periodo 2025')
        ->assertNoJavaScriptErrors();
})->with([
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('says so when nobody is out', function (): void {
    $url = signInToPanel();
    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());

    visitPage('leave/index', "{$url}/vacations")
        ->assertSee('Nadie está fuera hoy.')
        ->assertNoJavaScriptErrors();
});
