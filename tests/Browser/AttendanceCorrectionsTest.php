<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

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
        ->assertSee('Su semana aún no está confirmada: no se reabre nada.')
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
        ->assertSee('Cuando una persona pida corregir una marca');
})->with(['light' => [false], 'dark' => [true]]);

it('lets RH approve a request with its effect in view, adding the mark', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    $request = forgottenStart($ana, $yesterday);

    visit("{$url}/attendance/corrections")
        ->assertSee('Se agrega la marca de entrada de las 09:00 con evidencia Corregida')
        ->assertNoAccessibilityIssues(1)
        ->fill('note', 'Lo confirmó su jefa.')
        ->click('#approve-correction')
        ->waitForText('Solicitud aprobada')
        ->assertSee('Corrección aprobada.')
        ->assertSee('Nada por resolver')
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

    $page->click('a[href$="status=rejected"]')
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
        ->assertDontSee('Aprobar corrección');
});
