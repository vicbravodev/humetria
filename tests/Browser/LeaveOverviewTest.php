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
        ->assertSee('1 persona fuera hoy')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Esperan tu respuesta')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', '1 espera a su jefe')
        ->assertSeeIn('section[aria-labelledby="leave-ahead"]', 'Los próximos 14 días')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Días por disfrutar')
        ->assertSeeIn('section[aria-labelledby="leave-summary"]', 'Siguen en el saldo: oponer la prescripción es decisión de la empresa.')
        ->assertSee('1 persona no tiene fecha de ingreso: sin ella no se les otorgan días.')
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

it('opens a company that has not started on its three first steps', function (): void {
    $url = signInToPanel();
    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());

    visitPage('leave/index', "{$url}/vacations")
        ->assertSee('Revisa tu política')
        ->assertSee('Carga los saldos iniciales')
        ->assertSee('Comparte el portal')
        ->assertSee('Empezar por la política')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('says so when nothing waits for RH and nobody is out', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'hired_on' => '2020-01-15'])->id, 'years_of_service' => 1, 'anniversary_on' => '2026-03-15', 'statutory_days' => 12]);

    visitPage('leave/index', "{$url}/vacations")
        ->assertSee('Estás al día. Nadie espera respuesta.')
        ->assertSee('Nadie está fuera hoy ni esta semana')
        ->assertNoJavaScriptErrors();
});

it('lets RH approve from the overview a request that reached them', function (): void {
    $url = leaveOverviewPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $ivan = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Iván Castañeda', 'hired_on' => '2020-01-15']);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ivan->id, 'years_of_service' => 1, 'anniversary_on' => '2026-03-15', 'statutory_days' => 23]);
    LeaveRequest::factory()->escalated()->create(['tenant_id' => $tenant->id, 'employee_id' => $ivan->id, 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-20', 'working_days' => 3]);

    visitPage('leave/index', "{$url}/vacations")
        ->assertSee('1 solicitud espera tu respuesta. La más próxima empieza el 18 de noviembre.')
        ->assertSeeIn('section[aria-labelledby="leave-to-answer"]', 'Iván Castañeda')
        ->assertSeeIn('section[aria-labelledby="leave-to-answer"]', 'Sí le alcanza · le quedarían 20 días')
        ->assertSeeIn('section[aria-labelledby="leave-to-answer"]', 'Empieza en 14 días')
        ->assertNoJavaScriptErrors()
        ->click('section[aria-labelledby="leave-to-answer"] button:has-text("Aprobar")')
        ->click('[role=dialog] button:has-text("Sí, aprobar")')
        ->assertSee('Estás al día. Nadie espera respuesta.')
        ->assertNoJavaScriptErrors();

    expect(LeaveRequest::query()->where('employee_id', $ivan->id)->sole()->status->value)->toBe('approved');
});
