<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
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
 * Ana, who reports to Beto in a company that uses Vacaciones, on a Monday at noon in Mexico City: 3 days left of her
 * 2025 period, which could already prescribe, and 14 of the 2026 one.
 *
 * @return array{ana: Employee, boss: Employee, url: string}
 */
function vacationsPortal(bool $periods = true): array
{
    test()->travelTo('2026-11-02 18:00:00');
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $boss = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beto Ruiz']);
    $ana = Employee::factory()->reportingTo($boss)->create(['name' => 'Ana Solís']);

    if ($periods) {
        $older = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15', 'statutory_days' => 12]);
        LeaveEntry::factory()->of($older)->taken(9)->create();
        LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14]);
    }

    workIn($tenant);

    return ['ana' => $ana, 'boss' => $boss, 'url' => '/portal/'.resolve(IssuePortalLink::class)->handle($ana->id).'/vacations'];
}

// Boards LS-Portal and N037-Portal: one column, the person's own frame.
it('shows a person their days by period and what they asked for', function (bool $dark, bool $mobile): void {
    $portal = vacationsPortal();
    LeaveRequest::factory()->rejected()->create(['tenant_id' => $portal['ana']->tenant_id, 'employee_id' => $portal['ana']->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-05', 'working_days' => 5]);

    visitPage('leave/portal', $portal['url'], $dark, $mobile)
        ->assertSee('Mis vacaciones')
        // The person's own frame, even in a browser signed in to the panel: no sidebar, no top bar.
        ->assertNotPresent('[data-sidebar="sidebar"]')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', '17 días disponibles')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Periodo 2025')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Disfrútelos antes del 15 de septiembre de 2025.')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Podría prescribir')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Periodo 2026')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', '1 al 5 de junio de 2026 · 5 días laborables')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Rechazada')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Motivo: Cierre de mes')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('tells a person with no days yet when they will have them', function (): void {
    visitPage('leave/portal', vacationsPortal(periods: false)['url'])
        ->assertSee('Aún no tiene días disponibles')
        ->assertSee('Aún no ha pedido días.');
});
