<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Organization\Models\Employee;
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
        ->assertSee('Olvidó marcarla')
        ->assertSee('Aprobar')
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

it('lets RH approve a request from its dialog, adding the mark', function (): void {
    [$url, $ana, $yesterday] = correctionsPanel();
    $request = forgottenStart($ana, $yesterday);

    visit("{$url}/attendance/corrections")
        ->click('[aria-label="Aprobar la solicitud de Ana López"]')
        ->assertSee('Aprobar la corrección')
        // axe judges the dialog once its fade-in ended.
        ->assertScript('() => Promise.all(document.getAnimations().map((animation) => animation.finished)).then(() => true)')
        ->assertNoAccessibilityIssues(1)
        ->fill('note', 'Lo confirmó su jefa.')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Solicitud aprobada')
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
        ->click('[aria-label="Rechazar la solicitud de Ana López"]')
        ->assertSee('Rechazar la solicitud')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('El campo nota es obligatorio')
        ->fill('note', 'La cámara dice que llegaste a las 9:20.')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Solicitud rechazada');

    $page->click('a[href$="status=rejected"]')
        ->waitForText('La cámara dice que llegaste a las 9:20.')
        ->assertNoJavaScriptErrors();
    expect($request->fresh()?->status)->toBe(CorrectionStatus::Rejected);
});
