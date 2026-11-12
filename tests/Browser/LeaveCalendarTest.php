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

it('shows who is away each day of the month, as a grid on a desktop and by week on a phone', function (bool $dark, bool $mobile): void {
    $page = visitPage('leave/calendar', leaveCalendarPanel()['url'], $dark, $mobile)
        ->assertSee('Calendario del equipo')
        ->assertSee('noviembre de 2026')
        ->assertSee('Con ausencias')
        ->assertSee('Las 2')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);

    if ($mobile) {
        $page->assertDontSee('Ana Solís')
            ->click('button[aria-label="Semana siguiente"]')
            ->assertSee('Ana Solís')
            ->assertSee('9–13')
            ->assertNoJavaScriptErrors();

        return;
    }

    $page->assertSee('Ana Solís')
        ->assertSee('Fuera cada día')
        ->assertSee('Por responder')
        ->assertSee('2 personas con ausencias en noviembre')
        ->assertScript('document.querySelector("[aria-label=\"Ana Solís: Vacaciones, 9 al 13 de noviembre de 2026\"]") !== null', true)
        ->assertScript('document.querySelector("[role=region][aria-label=\"Calendario del equipo\"]").scrollWidth <= document.querySelector("[role=region][aria-label=\"Calendario del equipo\"]").clientWidth', true);
})->with($looks);

it('shows everybody with a click and comes back', function (): void {
    visitPage('leave/calendar', leaveCalendarPanel()['url'])
        ->click('nav[aria-label="A quién mostrar"] a:has-text("Las 2")')
        ->assertSee('Caro Luz')
        ->click('nav[aria-label="A quién mostrar"] a:has-text("Con ausencias")')
        ->assertSee('Ana Solís')
        ->assertNoJavaScriptErrors();
});

it('says so, with a way on, when a month has no absences', function (): void {
    $url = leaveCalendarPanel()['url'];

    visitPage('leave/calendar', "{$url}?month=2026-12")
        ->assertSee('Nadie tiene ausencias en diciembre')
        ->assertSee('Ir a solicitudes')
        ->assertSee('Hoy')
        ->assertNoJavaScriptErrors();
});

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
