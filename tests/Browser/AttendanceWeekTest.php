<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekPeriod;
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
    $monday = WeekPeriod::containing(CarbonImmutable::now()->subWeek())->start();
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
        ->assertDontSee('Ana López')
        ->assertDontSee('Su respuesta');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

/** Ana's phone, linked from the link RH sent her, then on Marcar, from where she opens her week and signs to see it. */
function anaSeesHerWeek(bool $dark = false): AwaitableWebpage
{
    $enroll = (string) parse_url(enrollmentUrl(test()->ana->id), PHP_URL_PATH);
    $page = visitPage('attendance/enroll', $enroll, $dark, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    $page->click('Leí el acuerdo y lo acepto.')->press('Aceptar y vincular este teléfono')->waitForText('Su teléfono quedó vinculado');

    return $page->navigate('/a/acme')
        ->click('Mi semana')
        ->waitForText('Ver mi semana')
        ->press('Ver mi semana')
        // Only the week read shows «Su respuesta» (the gate before it speaks of «sus marcas» too).
        ->waitForText('Su respuesta');
}

it('shows Ana her week with its hours and notices, and confirms it with her fingerprint', function (bool $dark): void {
    $page = anaSeesHerWeek($dark);

    $page->assertSee('Ana López')
        ->assertSee('Sin confirmar')
        ->assertSee('Salida a comer')
        ->assertSee('19:00')
        ->assertSee('Horas extra dobles')
        ->assertSee('Un día pasó de 12 horas de trabajo.')
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
        ->assertSee('corregida por RH')
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

    $page->click('[aria-label="Pedir corrección de salida de las 21:00"]')
        ->waitForText('¿A qué hora fue?')
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
        ->waitForText('estaba confirmada: se reabre y le pedimos confirmarla otra vez')
        ->click('#approve-correction')
        ->waitForText('Solicitud aprobada');

    $monday = WeekPeriod::containing(CarbonImmutable::now()->subWeek())->start()->toDateString();
    $page->navigate("/a/acme/week/{$monday}")
        ->press('Ver mi semana')
        ->waitForText('Su respuesta')
        ->assertSee('Sin confirmar')
        ->assertSee('corregida por RH')
        ->assertSee('Motivo de la corrección: El martes salí a las 20:00, el QR no cargaba.')
        ->assertSee('Aprobada')
        ->assertSee('Confirmar mi semana')
        ->assertNoJavaScriptErrors();

    expect(WeeklyConfirmation::query()->sole()->status)->toBe(WeekStatus::Pending);
});

it('lets a person whose mark was refused ask RH from Marcar to add it', function (): void {
    $enroll = (string) parse_url(enrollmentUrl($this->ana->id), PHP_URL_PATH);
    $page = visitPage('attendance/enroll', $enroll, mobile: true, reach: fn (AwaitableWebpage $page) => withFingerprintReader($page));
    $page->click('Leí el acuerdo y lo acepto.')->press('Aceptar y vincular este teléfono')->waitForText('Su teléfono quedó vinculado');

    // On site, with no code of the center's screen: the mark is refused.
    $page->navigate('/a/acme')
        ->press('Marcar entrada')
        ->waitForText('Pedir corrección a Recursos Humanos')
        ->press('Pedir corrección a Recursos Humanos')
        ->waitForText('¿Qué marca le faltó?')
        ->assertNoAccessibilityIssues(1)
        ->select('mark_type', 'start')
        ->fill('reason', 'Llegué a tiempo pero el teléfono no marcó.')
        ->press('Enviar solicitud')
        ->waitForText('Su solicitud llegó a Recursos Humanos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(CorrectionRequest::query()->sole())->type->toBe(MarkType::Start)->mark_id->toBeNull();
});
