<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * Beto, who leads Ana (14 days of her 2026 period) and Caro (no days), with three requests waiting for him: Ana's
 * vacations, Caro's paternity leave and Caro's vacations without the days.
 *
 * @return array{url: string, ana: LeaveRequest, leave: LeaveRequest, short: LeaveRequest}
 */
function leaveTeamPortal(): array
{
    test()->travelTo('2026-11-02 18:00:00');
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $boss = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beto Ruiz']);
    $ana = Employee::factory()->reportingTo($boss)->create(['name' => 'Ana Solís']);
    $caro = Employee::factory()->reportingTo($boss)->create(['name' => 'Caro Luz']);
    LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    $type = LeaveType::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Paternidad']);
    $waiting = ['tenant_id' => $tenant->id, 'approver_employee_id' => $boss->id];
    workIn($tenant);

    return [
        'url' => '/portal/'.resolve(IssuePortalLink::class)->handle($boss->id).'/vacations',
        'ana' => LeaveRequest::factory()->create([...$waiting, 'employee_id' => $ana->id, 'requested_on' => '2026-10-28', 'note' => 'Viaje familiar']),
        'leave' => LeaveRequest::factory()->ofType($type)->create([...$waiting, 'employee_id' => $caro->id, 'requested_on' => '2026-10-29', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-07', 'working_days' => 5]),
        'short' => LeaveRequest::factory()->create([...$waiting, 'employee_id' => $caro->id, 'requested_on' => '2026-10-30', 'starts_on' => '2027-01-11', 'ends_on' => '2027-01-14', 'working_days' => 4, 'had_balance' => false]),
    ];
}

// Boards LS-Portal and N037-Portal: one column, the person's own frame.
it('shows a manager the requests that wait for them, each with where its days would come from', function (bool $dark, bool $mobile): void {
    $team = leaveTeamPortal();

    visitPage('leave/portal-team', $team['url'].'/team', $dark, $mobile)
        ->assertSee('Solicitudes de mi equipo')
        ->assertNotPresent('[data-sidebar="sidebar"]')
        ->assertSeeIn('li:has-text("Viaje familiar")', 'Ana Solís')
        ->assertSeeIn('li:has-text("Viaje familiar")', '9 al 20 de noviembre de 2026 · 9 días laborables')
        ->assertSeeIn('li:has-text("Viaje familiar")', 'Tiene días disponibles: del periodo 2026 (9).')
        // Whoever approves does see which leave it is.
        ->assertSeeIn('li:has-text("Paternidad")', 'Caro Luz')
        ->assertSeeIn('li:has-text("Paternidad")', 'Este permiso no usa días de vacaciones.')
        // Without the days and with no advance in the policy, approving is not offered.
        ->assertSeeIn('li:has-text("11 al 14 de enero de 2027")', 'No tiene días disponibles (le faltan 4). Su política no permite adelantar días, así que no se puede aprobar.')
        ->assertNotPresent('li:has-text("11 al 14 de enero de 2027") button:has-text("Aprobar")')
        ->assertPresent('li:has-text("Viaje familiar") button:has-text("Aprobar")')
        ->assertSeeIn('li:has-text("11 al 14 de enero de 2027")', 'Rechazar')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('lets a manager approve one request and reject another with its reason', function (bool $dark, bool $mobile): void {
    $team = leaveTeamPortal();

    visitPage('leave/portal-team', $team['url'].'/team', $dark, $mobile)
        ->click('li:has-text("Viaje familiar") button:has-text("Aprobar")')
        ->assertSee('¿Aprobar la solicitud de Ana Solís?')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Solicitud aprobada.')
        ->assertDontSee('Viaje familiar')
        ->click('li:has-text("Paternidad") button:has-text("Rechazar")')
        ->assertSee('Caro Luz leerá el motivo que usted escriba.')
        // The reason is required: the dialog stays open and says so.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('El campo motivo es obligatorio.')
        ->type('[role=dialog] textarea', 'Cierre de año: tome la semana siguiente.')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Solicitud rechazada.')
        ->assertDontSee('Paternidad')
        ->assertSee('11 al 14 de enero de 2027')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($team['ana']->refresh()->status->value)->toBe('approved')
        ->and($team['leave']->refresh()->only(['rejection_reason']))->toBe(['rejection_reason' => 'Cierre de año: tome la semana siguiente.'])
        ->and($team['leave']->status->value)->toBe('rejected')
        ->and($team['short']->refresh()->status->value)->toBe('requested');
})->with($looks);

it('takes a manager from «Mis vacaciones» to the requests that wait for them', function (): void {
    $team = leaveTeamPortal();

    visitPage('leave/portal', $team['url'])
        ->assertSee('3 solicitudes esperan su respuesta.')
        ->click('Solicitudes de su equipo')
        ->assertPathEndsWith('/vacations/team')
        ->assertSee('Solicitudes de mi equipo')
        ->click('Volver a mis vacaciones')
        ->assertPathEndsWith('/vacations')
        ->assertNoJavaScriptErrors();
});

it('tells whoever has nothing to answer that nothing waits', function (bool $dark, bool $mobile): void {
    $team = leaveTeamPortal();
    LeaveRequest::query()->update(['approver_employee_id' => null]);

    visitPage('leave/portal-team', $team['url'].'/team', $dark, $mobile)
        ->assertSee('No tiene solicitudes por responder.')
        ->assertNotPresent('button:has-text("Rechazar")');
})->with($looks);
