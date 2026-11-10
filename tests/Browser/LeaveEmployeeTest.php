<?php

declare(strict_types=1);

use App\Modules\Leave\Enums\EntryKind;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Organization\Models\Employee;
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
 * The record of Ana on Wednesday 4 November 2026: a period with a ledger, an approved request and a statement without
 * acknowledgment; with a termination to settle when she left.
 *
 * @return array{url: string, tenant: Tenant, ana: Employee}
 */
function leaveEmployeePanel(bool $left = false): array
{
    test()->travelTo('2026-11-04 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    LeaveEntry::factory()->of($period)->taken(2)->create();
    LeaveRequest::factory()->approved()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-08', 'working_days' => 2]);
    LeaveStatement::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'leave_entitlement_id' => $period->id, 'acknowledged_at' => null]);
    LeaveEntry::factory()->of($period)->create(['kind' => EntryKind::Adjustment, 'days' => 1, 'note' => 'Día por antigüedad', 'recorded_by' => auth()->id()]);

    if ($left) {
        $ana->update(['deactivated_at' => '2026-10-30 18:00:00']);
        LeaveTermination::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'terminated_on' => '2026-10-30', 'pending_days' => 13, 'proportional_days' => 5.5]);
    }

    return ['url' => "{$url}/vacations/employees/{$ana->id}", 'tenant' => $tenant, 'ana' => $ana];
}

it('shows RH the record of a person: periods, ledger, requests and statements', function (bool $dark, bool $mobile): void {
    visitPage('leave/employee', leaveEmployeePanel()['url'], $dark, $mobile)
        ->assertSee('Ana Solís')
        ->assertSee('Periodo 2026')
        ->assertSee('Movimientos de sus días')
        ->assertSee('Día por antigüedad')
        ->assertSee('Constancia del periodo 2026')
        ->assertSee('Sin acuse')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('shows what a person who left is owed, in days', function (bool $dark, bool $mobile): void {
    visitPage('leave/employee', leaveEmployeePanel(left: true)['url'], $dark, $mobile)
        ->assertSee('Baja')
        ->assertSee('Días al terminar la relación')
        ->assertSee('Por finiquitar')
        ->assertSee('18.5 en total')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('lets RH adjust a period and see it in the ledger', function (): void {
    $record = leaveEmployeePanel();

    visitPage('leave/employee', $record['url'])
        ->click('button[aria-label="Ajustar el periodo 2026"]')
        ->type('#adjust-days', '2')
        ->type('#adjust-note', 'Antigüedad reconocida')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Ajuste registrado.')
        ->assertSee('Antigüedad reconocida')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(LeaveEntry::query()->where('note', 'Antigüedad reconocida')->count())->toBe(1);
});

it('says why an adjustment cannot be saved', function (): void {
    visitPage('leave/employee', leaveEmployeePanel()['url'])
        ->click('button[aria-label="Ajustar el periodo 2026"]')
        ->type('#adjust-days', '2')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('nota')
        ->assertNoJavaScriptErrors();
});

it('lets RH settle the days of a person who left', function (): void {
    $record = leaveEmployeePanel(left: true);

    visitPage('leave/employee', $record['url'])
        ->press('Finiquitar los días')
        ->click('[role=dialog] button:has-text("Sí, finiquitar")')
        ->assertSee('Días finiquitados.')
        ->assertSee('Finiquitado el')
        ->assertDontSee('Finiquitar los días')
        ->assertNoJavaScriptErrors();

    expect(LeaveTermination::query()->whereNotNull('settled_at')->count())->toBe(1);
});

it('does not offer a viewer to adjust or settle', function (): void {
    $record = leaveEmployeePanel(left: true);
    test()->actingAs(memberOf($record['tenant'], App\Modules\Tenancy\Enums\TenantRole::Viewer));

    visitPage('leave/employee', $record['url'])
        ->assertSee('Periodo 2026')
        ->assertDontSee('Ajustar')
        ->assertDontSee('Finiquitar los días');
});
