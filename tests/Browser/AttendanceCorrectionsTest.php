<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Tests\Support\OfflineDeliveries;

/** The panel of the company RH signed in to, with Ana, who forgot to mark yesterday's start. */
function correctionsPanel(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora']);
    $yesterday = CarbonImmutable::now('America/Mexico_City')->subDay()->startOfDay();
    AttendanceMark::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'work_center_id' => $plant->id, 'type' => MarkType::End, 'marked_at' => $yesterday->setTime(18, 0)->utc()]);

    return [$url, $ana, $yesterday];
}

function forgottenStart(Employee $ana, CarbonImmutable $day): CorrectionRequest
{
    return CorrectionRequest::factory()->create([
        'tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'type' => MarkType::Start,
        'proposed_at' => $day->setTime(9, 0)->utc(), 'reason' => 'Olvidé marcar mi entrada, el QR no cargaba.',
    ]);
}

it('shows RH the queue of corrections with its states and each request', function (bool $dark, bool $mobile): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    forgottenStart($ana, $yesterday);

    visitPage('attendance/corrections', "{$url}/attendance/corrections", $dark, $mobile)
        ->assertSee('Correcciones')
        ->assertSee('Por resolver')
        ->assertSee('Ana López')
        ->assertSee('Operadora')
        ->assertSee('Olvidé marcar mi entrada, el QR no cargaba.')
        ->assertSee('olvidó marcarla')
        ->assertSee('Ese día en el registro')
        ->assertSee('Si la apruebas')
        ->assertSee('Su semana aún no tiene respuesta: no se reabre nada.')
        ->assertSee('Ana no tiene horario')
        ->assertSee('Si la rechazas')
        ->assertSee('Aprobar corrección')
        ->assertSee('Rechazar');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('shows an empty queue with what will appear in it', function (bool $dark): void {
    [$url] = correctionsPanel();

    visitPage('attendance/corrections', "{$url}/attendance/corrections", $dark)
        ->assertSee('Nada por resolver')
        ->assertSee('Cuando una persona pida corregir una marca')
        ->assertDontSee('resuelta')
        // With no request to show there is no detail beside it: the empty queue takes the whole width, leaving no hole.
        ->assertScript('() => { const empty = [...document.querySelectorAll("h2, h3")].find((title) => title.textContent === "Nada por resolver").closest("section").getBoundingClientRect(); const header = document.querySelector("h1").closest("[data-slot=page-body] > div").getBoundingClientRect(); return empty.width > header.width * 0.8; }', true);
})->with(['light' => [false], 'dark' => [true]]);

it('lets RH approve a request with its effect in view, adding the mark', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    $request = forgottenStart($ana, $yesterday);

    accessible(visit("{$url}/attendance/corrections")
        ->assertSee('Se agrega la marca de entrada de las 09:00 con evidencia Corregida'), 1)
        ->fill('note', 'Lo confirmó su jefa.')
        ->click('#approve-correction')
        ->waitForText('Solicitud aprobada')
        ->assertSee('Corrección aprobada.')
        ->assertSee('Nada por resolver')
        ->assertSee('Ver la resuelta')
        ->click('a:has-text("Ver la resuelta")')
        ->waitForText('Lo confirmó su jefa.')
        ->assertDontSee('Nada por resolver')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect($request->fresh()?->status)->toBe(CorrectionStatus::Approved)
        ->and(AttendanceMark::query()->whereKey($request->fresh()?->resulting_mark_id)->sole()->type)->toBe(MarkType::Start);
});

it('asks RH for a note to reject, and keeps it with the request', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    $request = forgottenStart($ana, $yesterday);

    $page = visit("{$url}/attendance/corrections")
        ->click('#reject-correction')
        ->waitForText('El campo nota es obligatorio')
        ->fill('note', 'La cámara dice que llegaste a las 9:20.')
        ->click('#reject-correction')
        ->waitForText('Solicitud rechazada')
        ->assertSee('Corrección rechazada.');

    $page->click('nav[aria-label="Estado de las solicitudes"] a[href$="status=rejected"]')
        ->waitForText('La cámara dice que llegaste a las 9:20.')
        ->assertNoJavaScriptErrors();
    expect($request->fresh()?->status)->toBe(CorrectionStatus::Rejected);
});

it('warns before RH approves what the server would refuse, and keeps the approval from being sent', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    // Ana\'s end is at 18:00: an end asked for 08:00 would come before any start of the day.
    CorrectionRequest::factory()->create([
        'tenant_id' => $ana->tenant_id, 'employee_id' => $ana->id, 'type' => MarkType::End,
        'proposed_at' => $yesterday->setTime(8, 0)->utc(), 'reason' => 'Salí a las 8, olvidé marcar mi salida.',
    ]);

    visit("{$url}/attendance/corrections")
        ->assertSee('no se podrá aprobar')
        ->assertScript('() => document.querySelector("#approve-correction").disabled', true)
        ->assertSee('Rechazar')
        ->assertNoJavaScriptErrors();
});

it('shows each request of the queue in its turn, with its own effect, without leaving the page', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    forgottenStart($ana, $yesterday);
    $bob = EmployeeFactory::new()->create(['tenant_id' => $ana->tenant_id, 'work_center_id' => $ana->work_center_id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    CorrectionRequest::factory()->create([
        'tenant_id' => $ana->tenant_id, 'employee_id' => $bob->id, 'type' => MarkType::Start,
        'proposed_at' => $yesterday->setTime(10, 0)->utc(), 'reason' => 'El QR no cargaba.', 'created_at' => now()->addMinute(),
    ]);

    visit("{$url}/attendance/corrections")
        ->assertSee('Ana López pide agregar su entrada')
        ->click('Bob Díaz')
        ->waitForText('Bob Díaz pide agregar su entrada')
        ->assertSee('Se agrega la marca de entrada de las 10:00')
        ->assertSee('El día seguiría sin marca de salida hasta que Bob agregue la suya.')
        ->assertNoJavaScriptErrors();
});

it('shows a viewer the request and its effect, without the decision', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    forgottenStart($ana, $yesterday);
    $this->actingAs(memberOf(Tenant::query()->where('slug', 'acme')->sole(), TenantRole::Viewer));

    visitPage('attendance/corrections', "{$url}/attendance/corrections")
        ->assertSee('Si la apruebas')
        ->assertSee('Solo el dueño y RH resuelven las solicitudes')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertDontSee('Aprobar corrección');
});

/** A mark Ana made without a connection yesterday at a local time, in the queue to verify. */
function anaOffline(Employee $ana, CarbonImmutable $day, string $time = '09:00', bool $qr = false, array $flags = []): void
{
    OfflineDeliveries::accepted($ana, $day->setTimeFromTimeString($time)->format('Y-m-d H:i'), qr: $qr, flags: $flags, justification: 'Se me fue la señal en el túnel.');
}

it('shows RH the marks made without a connection with their badge, flags and every hour, in light, dark, desktop and phone', function (bool $dark, bool $mobile): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    anaOffline($ana, $yesterday, qr: true, flags: [ReviewFlag::NoPosition, ReviewFlag::DeclaredAdjusted]);

    visitPage('attendance/corrections', "{$url}/attendance/corrections?status=offline", $dark, $mobile)
        ->assertSee('Marcas sin conexión')
        ->assertSee('Sin conexión · por verificar')
        ->assertSee('Sin posición')
        ->assertSee('La hora contada no es la declarada')
        ->assertSee('Ana López marcó su entrada sin conexión')
        ->assertSee('Hora declarada')
        ->assertSee('Sin conexión desde')
        ->assertSee('No antes de')
        ->assertSee('Firmada con su llave de acceso')
        ->assertSee('Se me fue la señal en el túnel.')
        ->assertSee('Verificar no cambia la marca')
        ->assertSee('Es incorrecta')
        ->assertSee('Verificar');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('shows the tab of marks made without a connection empty, with what will appear in it', function (): void {
    [$url] = correctionsPanel();

    visitPage('attendance/corrections', "{$url}/attendance/corrections?status=offline")
        ->assertSee('Nada por verificar')
        ->assertSee('Las que lleguen aparecerán aquí.');
});

it('lets RH verify a mark made without a connection, which leaves the queue', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    anaOffline($ana, $yesterday);

    accessible(visit("{$url}/attendance/corrections?status=offline")
        ->assertSee('Sin conexión · por verificar'), 1)
        ->click('#verify-mark')
        ->waitForText('Marca verificada.')
        ->assertSee('Nada por verificar')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(MarkReview::query()->sole()->note)->toBeNull();
});

it('lets RH say a mark is incorrect with its note and the right time, and keeps the original', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    anaOffline($ana, $yesterday);

    visit("{$url}/attendance/corrections?status=offline")
        ->click('#mark-incorrect')
        ->waitForText('Esta marca es incorrecta')
        ->assertSee('Hora correcta')
        ->fill('note', 'La cámara la muestra entrando a las 9:20.')
        ->fill('#offline-correct-time', '9:20')
        ->click('button:has-text("Corregir marca")')
        ->waitForText('Marca corregida.')
        ->assertSee('Nada por verificar')
        ->assertNoJavaScriptErrors();

    $correction = AttendanceMark::query()->whereNotNull('corrects_mark_id')->sole();
    expect($correction->reason)->toBe('La cámara la muestra entrando a las 9:20.')
        ->and($correction->marked_at?->setTimezone('America/Mexico_City')->format('H:i'))->toBe('09:20');
});

it('lets RH leave only the marks with no flags and verify the selected ones together', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    $bob = EmployeeFactory::new()->create(['tenant_id' => $ana->tenant_id, 'work_center_id' => $ana->work_center_id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista']);
    $eva = EmployeeFactory::new()->create(['tenant_id' => $ana->tenant_id, 'work_center_id' => $ana->work_center_id, 'name' => 'Eva Ruiz', 'job_title' => 'Cajera']);
    anaOffline($ana, $yesterday, flags: [ReviewFlag::WideInterval]);
    anaOffline($bob, $yesterday);
    anaOffline($eva, $yesterday);

    visit("{$url}/attendance/corrections?status=offline")
        ->click('a:has-text("Sin banderas")')
        ->waitForText('Bob Díaz')
        ->assertDontSee('Más de 4 h sin conexión')
        ->click('button[aria-label="Elegir la marca de Bob Díaz"]')
        ->click('button[aria-label="Elegir la marca de Eva Ruiz"]')
        ->assertSee('2 marcas elegidas')
        ->click('button:has-text("Verificar seleccionadas")')
        ->waitForText('Verificaste 2 marcas.')
        ->assertNoJavaScriptErrors();

    expect(MarkReview::query()->count())->toBe(2);
});

it('shows the deliveries that were not accepted with why, and no way to resolve them', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    OfflineDeliveries::accepted($ana, $yesterday->setTime(9, 0)->format('Y-m-d H:i'));
    OfflineDeliveries::rejected($ana, $yesterday->setTime(10, 0)->format('Y-m-d H:i'), OfflineRejection::WrongCenter);

    visitPage('attendance/corrections', "{$url}/attendance/corrections?status=not_accepted")
        ->assertSee('Sin conexión · no aceptada')
        ->assertSee('Marcó en un centro que no es el suyo')
        ->assertSee('Ana López')
        ->assertDontSee('Es incorrecta');
});

it('shows a viewer the marks made without a connection, without the boxes, the decision nor what the person wrote', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    anaOffline($ana, $yesterday);
    $this->actingAs(memberOf(Tenant::query()->where('slug', 'acme')->sole(), TenantRole::Viewer));

    visitPage('attendance/corrections', "{$url}/attendance/corrections?status=offline")
        ->assertSee('Sin conexión · por verificar')
        ->assertSee('Solo lectura')
        ->assertSee('Solo el dueño y RH verifican las marcas')
        ->assertDontSee('Seleccionar la página')
        ->assertDontSee('Se me fue la señal en el túnel.')
        ->assertDontSee('Verificar seleccionadas');
});
