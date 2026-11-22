<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;

beforeEach(function (): void {
    Playwright::setHost('localhost');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    // Last week: Monday 9:00 to 19:00 with an hour's break (an hour over), Tuesday 7:00 to 21:00 (14 hours).
    $monday = WeekPeriod::containing(CarbonImmutable::now()->subWeek(), 'America/Mexico_City')->start();
    foreach ([[MarkType::Start, 0, '09:00'], [MarkType::BreakStart, 0, '14:00'], [MarkType::BreakEnd, 0, '15:00'], [MarkType::End, 0, '19:00'], [MarkType::Start, 1, '07:00'], [MarkType::End, 1, '21:00']] as [$type, $day, $time]) {
        AttendanceMark::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id,
            'type' => $type, 'marked_at' => $monday->addDays($day)->setTimeFromTimeString($time)->utc(),
        ]);
    }
});

afterEach(function (): void {
    Playwright::setHost(null);
});

it('opens «Mi semana» showing nothing of anybody until the device signs', function (bool $dark, bool $mobile): void {
    visitPage('attendance/week', '/a/acme/week', $dark, $mobile)
        ->assertSee('Mi semana')
        ->assertSee('Ver mi semana')
        // A state screen, centered, with the way back to Marcar at the top (hallazgo 17): not a button in its body.
        ->assertPresent('header a[href$="/a/acme"]')
        ->assertDontSee('Volver a Marcar')
        ->assertDontSee('Ana López')
        ->assertDontSee('Total de la semana')
        ->assertNoAccessibilityIssues(1);
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

/** Ana's phone, linked from the link RH sent her, then on Marcar, from where she opens her week and signs to see it. */
function anaSeesHerWeek(bool $dark = false): AwaitableWebpage
{
    $enroll = accessLinkPath(test()->ana->id);
    $page = visitPage('worker-access/link', $enroll, $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, test()->ana->id);

    return $page->navigate('/a/acme')
        ->waitForText('Vea sus marcas y sus horas')
        ->click('Vea sus marcas y sus horas')
        ->waitForText('Ver mi semana')
        ->press('Ver mi semana')
        // Only the week read shows its total.
        ->waitForText('Total de la semana');
}

it('tells Ana how late she marked her start, when her schedule has a start time, and nothing on the other days', function (): void {
    // Monday starts at 08:00 and she marked at 09:00: an hour late, past the default tolerance of 10 minutes.
    AttendanceSchedule::query()->update(['starts_by_weekday' => [1 => 480, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null]]);

    // A day with a lateness opens by itself.
    anaSeesHerWeek()->assertSee('Retardo de 1 h')
        ->assertDontSee('Retardo de 2 h')
        ->assertNoJavaScriptErrors();
});

it('shows Ana her week with its hours and notices, and confirms it with her fingerprint', function (bool $dark): void {
    $page = anaSeesHerWeek($dark);

    $page->assertSee('Su semana')
        ->assertSee('Ana López')
        ->assertSee('Total de la semana')
        // The shape of the week first: seven bars; the status beside the title; the days folded, one line each (hallazgo 21).
        ->assertPresent('[role="img"][aria-label^="Horas por día"]')
        ->assertSee('Sin confirmar')
        ->assertSee('09:00 a 19:00')
        ->assertSee('h extra')
        ->assertSee('Un día pasó de 12 horas de trabajo.')
        ->assertPresent('[aria-label="Semana anterior"]')
        ->assertDontSee('Evidencia')
        ->assertDontSee('Salida a comer')
        ->click('summary:has-text("09:00 a 19:00")')
        ->assertSee('Salida a comer')
        ->assertSee('Me faltó una marca')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '')
        ->assertNoAccessibilityIssues(1);

    $page->press('Confirmar mi semana')
        ->waitForText('La confirmó el')
        ->assertSee('Confirmada')
        ->assertDontSee('Confirmar mi semana')
        ->assertSee('No estoy de acuerdo')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);

    expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Confirmed);
})->with(['light' => [false], 'dark' => [true]]);

it('shows Ana a day of approved vacations as vacations, not as a day without marks', function (bool $dark): void {
    $wednesday = WeekPeriod::containing(CarbonImmutable::now()->subWeek(), 'America/Mexico_City')->start()->addDays(2)->toDateString();
    LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'starts_on' => $wednesday, 'ends_on' => $wednesday, 'working_days' => 1]);

    anaSeesHerWeek($dark)
        ->assertSee('Vacaciones')
        ->assertPresent('[role="img"][aria-label*="miércoles ausente"]')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertNoAccessibilityIssues(1);
})->with(['light' => [false], 'dark' => [true]]);

it('lets Ana say what is not right, with her reason', function (): void {
    $page = anaSeesHerWeek();

    $page->press('No estoy de acuerdo')
        ->waitForText('¿Qué no está bien en su semana?')
        ->assertNoAccessibilityIssues(1)
        ->fill('reason', 'El martes salí a las 20:00, no a las 21:00.')
        ->press('Enviar mi objeción')
        ->waitForText('La objetó el')
        ->assertSee('Objetada')
        ->assertSee('El martes salí a las 20:00, no a las 21:00.')
        ->assertDontSee('Confirmar mi semana')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(WeeklyConfirmation::query()->sole())->status->toBe(WeekStatus::Disputed)->reason->toBe('El martes salí a las 20:00, no a las 21:00.');
});

it('shows Ana her week again when RH corrected it after she opened it, and lets her confirm the new one', function (): void {
    $page = anaSeesHerWeek();
    $tuesdayEnd = AttendanceMark::query()->where('type', MarkType::End)->orderByDesc('marked_at')->firstOrFail();
    AttendanceMark::factory()->correcting($tuesdayEnd)->create(['marked_at' => $tuesdayEnd->marked_at?->subHour()]);

    $page->press('Confirmar mi semana')
        ->waitForText('Su semana cambió desde que la abrió')
        // The day with a correction opens by itself.
        ->assertSee('Corregida por RH')
        ->assertSee('Sin confirmar')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(1);
    expect(WeeklyConfirmation::query()->count())->toBe(0);

    $page->press('Confirmar mi semana')->waitForText('La confirmó el');
    expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Confirmed);
});

it('lets Ana ask RH to correct a mark of a week she confirmed; once RH approves, her week shows it and asks her again', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $page = anaSeesHerWeek();
    $page->press('Confirmar mi semana')->waitForText('La confirmó el');

    $page->click('summary:has-text("07:00 a 21:00")')
        ->click('[aria-label="Pedir corrección de salida de las 21:00"]')
        // A sheet from the bottom, one mark at a time (hallazgo 22).
        ->waitForText('Corregir salida de las 21:00')
        ->assertSee('¿A qué hora fue?')
        ->assertNoAccessibilityIssues(1)
        ->fill('time', '20:00')
        ->fill('reason', 'El martes salí a las 20:00, el QR no cargaba.')
        ->press('Enviar solicitud')
        ->waitForText('Su solicitud llegó a Recursos Humanos')
        ->assertSee('Sus solicitudes de corrección')
        ->assertSee('En revisión')
        ->assertDontSee('[aria-label="Pedir corrección de salida de las 21:00"]')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->assertScript(smallTouchTargets(), '');

    $page->navigate('/admin/acme/attendance/corrections')
        ->waitForText('El martes salí a las 20:00, el QR no cargaba.')
        // The panel says first that her confirmed week reopens.
        ->waitForText('ya tenía respuesta: se reabre y le pedimos responderla otra vez')
        ->click('#approve-correction')
        ->waitForText('Solicitud aprobada');

    $monday = WeekPeriod::containing(CarbonImmutable::now()->subWeek(), 'America/Mexico_City')->start()->toDateString();
    $page->navigate("/a/acme/week/{$monday}")
        ->waitForText('Ver mi semana')
        ->press('Ver mi semana')
        ->waitForText('Total de la semana')
        ->assertSee('Sin confirmar')
        ->assertSee('Corregida por RH')
        ->assertSee('Motivo de la corrección: El martes salí a las 20:00, el QR no cargaba.')
        ->assertSee('Aprobada')
        ->assertSee('Confirmar mi semana')
        ->assertNoJavaScriptErrors();

    expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Pending);
});

it('lets a person whose mark was refused ask RH from Marcar to add it', function (): void {
    $enroll = accessLinkPath($this->ana->id);
    $page = visitPage('worker-access/link', $enroll, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    linkThePhone($page, $this->ana->id);

    // On site, with no code of the center's screen: the mark is refused.
    $page->navigate('/a/acme')
        ->waitForText('Regresar de comer')
        ->press('button:has-text("Entrada")')
        ->waitForText('Pedir una corrección')
        ->press('Pedir una corrección')
        ->waitForText('¿Qué marca le faltó?')
        ->assertNoAccessibilityIssues(1)
        ->click('label:has-text("Entrada")')
        ->fill('reason', 'Llegué a tiempo pero el teléfono no marcó.')
        ->press('Enviar solicitud')
        ->waitForText('Su solicitud llegó a Recursos Humanos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(CorrectionRequest::query()->sole())->type->toBe(MarkType::Start)->mark_id->toBeNull();
});
