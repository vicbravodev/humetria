<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
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
 * Ana, who reports to Beto in a company that uses Vacaciones, on a Monday at noon in Mexico City: 3 days left of her
 * 2025 period, which could already prescribe, and 14 of the 2026 one.
 *
 * @return array{ana: Employee, boss: Employee, url: string}
 */
function leaveVacationsPortal(bool $periods = true): array
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
    $portal = leaveVacationsPortal();
    LeaveRequest::factory()->rejected()->create(['tenant_id' => $portal['ana']->tenant_id, 'employee_id' => $portal['ana']->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-05', 'working_days' => 5]);

    LeaveRequest::factory()->create(['tenant_id' => $portal['ana']->tenant_id, 'employee_id' => $portal['ana']->id, 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-22', 'working_days' => 2]);

    visitPage('leave/portal', $portal['url'], $dark, $mobile)
        ->assertSee('Mis vacaciones')
        // The person's own frame, even in a browser signed in to the panel: no sidebar, no top bar.
        ->assertNotPresent('[data-sidebar="sidebar"]')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', '17 días disponibles')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Periodo 2025')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Disfrútelos antes del 15 de septiembre de 2025.')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Podría prescribir')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', 'Periodo 2026')
        ->assertSeeIn('section[aria-labelledby="leave-ask"]', 'Enviar solicitud')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', '21 al 22 de diciembre de 2026 · 2 días laborables')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Cancelar solicitud')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', '1 al 5 de junio de 2026 · 5 días laborables')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Rechazada')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Motivo: Cierre de mes')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('tells a person with no days yet when they will have them', function (): void {
    visitPage('leave/portal', leaveVacationsPortal(periods: false)['url'])
        ->assertSee('Aún no tiene días disponibles')
        ->assertSee('Aún no ha pedido días.');
});

it('lets a person ask for days and see the request waiting', function (bool $dark, bool $mobile): void {
    $portal = leaveVacationsPortal();

    visitPage('leave/portal', $portal['url'], $dark, $mobile)
        // The button waits for both dates, and says so instead of just being off.
        ->assertSee('Elija el día en que empieza y el día en que termina para poder enviar la solicitud.')
        // The dates are picked in a calendar, never typed; it opens on the month of the first day it allows.
        ->click('#leave-starts-on')
        ->click('[data-state="open"] [data-day="2026-11-09"] button')
        ->assertScript(calendarClosed(), true)
        ->click('#leave-ends-on')
        ->click('[data-state="open"] [data-day="2026-11-20"] button')
        ->assertScript(calendarClosed(), true)
        ->assertSee('Son 9 días laborables.')
        ->assertDontSee('Elija el día en que empieza y el día en que termina')
        // A reminder that never stops the request.
        ->assertSee('La ley le da derecho a 12 días seguidos de vacaciones. Dividirlos es decisión suya.')
        ->assertDontSee('No tiene días disponibles para estas fechas.')
        ->type('#leave-note', 'Viaje familiar')
        ->press('Enviar solicitud')
        ->assertSee('Enviamos su solicitud. Le avisaremos cuando la respondan.')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Solicitada')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', '9 al 20 de noviembre de 2026 · 9 días laborables')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(LeaveRequest::query()->sole()->only(['employee_id', 'note', 'working_days']))->toBe(['employee_id' => $portal['ana']->id, 'note' => 'Viaje familiar', 'working_days' => 9]);
})->with($looks);

it('says a person has no days for the dates and still lets them send the request', function (): void {
    $portal = leaveVacationsPortal(periods: false);

    visitPage('leave/portal', $portal['url'])
        ->click('#leave-starts-on')
        ->click('[data-state="open"] [data-day="2026-11-09"] button')
        ->assertScript(calendarClosed(), true)
        ->click('#leave-ends-on')
        ->click('[data-state="open"] [data-day="2026-11-10"] button')
        ->assertScript(calendarClosed(), true)
        ->assertSee('Son 2 días laborables.')
        ->assertSee('No tiene días disponibles para estas fechas. Puede enviar la solicitud; quien la apruebe lo verá.')
        ->press('Enviar solicitud')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Solicitada')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'No tenía días disponibles al pedirla; quien la apruebe lo verá.');

    expect(LeaveRequest::query()->sole()->had_balance)->toBeFalse();
});

it('says why dates cannot be asked as soon as they are picked', function (): void {
    visitPage('leave/portal', leaveVacationsPortal()['url'])
        ->click('#leave-starts-on')
        ->click('[data-state="open"] [data-day="2026-11-07"] button')
        ->assertScript(calendarClosed(), true)
        ->click('#leave-ends-on')
        ->click('[data-state="open"] [data-day="2026-11-08"] button')
        ->assertScript(calendarClosed(), true)
        ->assertSee('Esas fechas no incluyen ningún día laborable.')
        ->assertDontSee('días laborables.');
});

it('lets a person ask for a leave of the company, which uses none of their days', function (): void {
    $portal = leaveVacationsPortal();
    LeaveType::factory()->create(['tenant_id' => $portal['ana']->tenant_id, 'name' => 'Paternidad']);

    visitPage('leave/portal', $portal['url'])
        ->click('#leave-type')
        ->click('[role="option"]:has-text("Paternidad")')
        ->click('#leave-starts-on')
        ->click('[data-state="open"] [data-day="2026-11-09"] button')
        ->assertScript(calendarClosed(), true)
        ->click('#leave-ends-on')
        ->click('[data-state="open"] [data-day="2026-11-10"] button')
        ->assertScript(calendarClosed(), true)
        ->assertSee('Son 2 días laborables.')
        ->assertDontSee('La ley le da derecho a 12 días seguidos')
        ->press('Enviar solicitud')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Paternidad')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Solicitada');
});

it('lets a person cancel a request of theirs, after asking', function (bool $dark, bool $mobile): void {
    $portal = leaveVacationsPortal();
    $request = LeaveRequest::factory()->create(['tenant_id' => $portal['ana']->tenant_id, 'employee_id' => $portal['ana']->id]);

    visitPage('leave/portal', $portal['url'], $dark, $mobile)
        ->press('Cancelar solicitud')
        ->assertSee('¿Cancelar esta solicitud?')
        // The answer that goes ahead is the dialog's submit: its text has a comma, which a text selector would split.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Su solicitud quedó cancelada.')
        ->assertSeeIn('section[aria-labelledby="leave-requests"]', 'Cancelada')
        ->assertDontSee('Cancelar solicitud')
        ->assertNoJavaScriptErrors();

    expect($request->refresh()->status->value)->toBe('cancelled');
})->with($looks);

// E-02: the home of the portal lists the person's tools, and every tool leads back to it.
it('leads from the home of the portal to vacations and back', function (bool $dark, bool $mobile): void {
    $portal = leaveVacationsPortal();
    $home = preg_replace('#/vacations$#', '', $portal['url']) ?? '';

    visitPage('telework/portal', $home, $dark, $mobile)
        ->assertSee('Hola, Ana Solís')
        ->assertSee('Mis vacaciones')
        ->assertDontSee('teletrabajo')
        ->click('Mis vacaciones')
        ->assertSee('Sus días')
        ->click('Volver a mi portal')
        ->assertSee('Hola, Ana Solís')
        ->assertSee('Mis vacaciones')
        ->assertNoJavaScriptErrors();
})->with($looks);

// E-10 and E-11: days asked for and not answered are told apart, and a second tap right after sending finds nothing to send.
it('tells the days waiting for an answer and leaves nothing to send twice', function (): void {
    $portal = leaveVacationsPortal();

    $page = visitPage('leave/portal', $portal['url'])
        ->assertButtonDisabled('Enviar solicitud')
        ->click('#leave-starts-on')
        ->click('[data-state="open"] [data-day="2026-11-09"] button')
        ->assertScript(calendarClosed(), true)
        ->click('#leave-ends-on')
        ->click('[data-state="open"] [data-day="2026-11-20"] button')
        ->assertButtonEnabled('Enviar solicitud')
        ->press('Enviar solicitud')
        ->assertSee('Enviamos su solicitud.')
        ->assertSeeIn('section[aria-labelledby="leave-balance"]', '9 días en espera de respuesta')
        ->assertButtonDisabled('Enviar solicitud');

    $page->assertDontSee('Seleccione')->assertNoJavaScriptErrors();
});
