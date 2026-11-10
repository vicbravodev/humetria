<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Enums\TenantRole;
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
 * The calendar of November 2026 (today is Wednesday the 4th): Ana is away from the 9th to the 13th, Caro asked for the 23rd.
 *
 * @return array{url: string, tenant: Tenant}
 */
function leaveCalendarPanel(): array
{
    test()->travelTo('2026-11-04 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís']);
    $caro = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Caro Luz']);
    LeaveRequest::factory()->approved()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-13', 'working_days' => 5]);
    LeaveRequest::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $caro->id, 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-24', 'working_days' => 2]);

    return ['url' => "{$url}/vacations/calendar", 'tenant' => $tenant];
}

it('shows who is away each day of the month', function (bool $dark, bool $mobile): void {
    visitPage('leave/calendar', leaveCalendarPanel()['url'], $dark, $mobile)
        ->assertSee('Calendario del equipo')
        ->assertSee('noviembre de 2026')
        ->assertSee('Ana Solís')
        ->assertSee('Fuera cada día')
        ->assertSee('Por responder')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertScript('document.querySelector("[aria-label=\"Ana Solís: Vacaciones, 9 al 13 de noviembre de 2026\"]") !== null', true);
})->with($looks);

it('moves to the next and to the previous month', function (): void {
    visitPage('leave/calendar', leaveCalendarPanel()['url'])
        ->click('a[aria-label="Mes siguiente"]')
        ->assertSee('diciembre de 2026')
        ->click('a[aria-label="Mes anterior"]')
        ->assertSee('noviembre de 2026')
        ->click('a[aria-label="Mes anterior"]')
        ->assertSee('octubre de 2026')
        ->assertNoJavaScriptErrors();
});

it('does not draw the requests that wait for a viewer', function (): void {
    $calendar = leaveCalendarPanel();
    test()->actingAs(memberOf($calendar['tenant'], TenantRole::Viewer));

    visitPage('leave/calendar', $calendar['url'])
        ->assertSee('Ana Solís')
        ->assertDontSee('Por responder')
        ->assertScript('document.querySelector("[aria-label*=\"por responder\"]") === null', true);
});
