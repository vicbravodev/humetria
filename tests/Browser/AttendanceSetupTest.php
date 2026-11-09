<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with Planta Norte (a zone, no screen), Oficinas (nothing) and three people, none with a schedule or a phone. */
function setupPanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $office = WorkCenter::factory()->for($tenant)->create(['name' => 'Oficinas']);
    AttendanceZone::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Nave 1', 'radius_m' => 80]);

    foreach ([[$plant, 'Ana López'], [$plant, 'Bob Díaz'], [$office, 'Elena Vega']] as [$center, $name]) {
        EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'name' => $name]);
    }

    return $url;
}

it('shows RH the four steps to the first mark, what each lacks and how many days are left', function (bool $dark, bool $mobile): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-08 18:00:00', 'UTC'));
    $url = setupPanel();

    visitPage('attendance/setup', "{$url}/attendance/setup", $dark, $mobile)
        ->assertSee('Puesta en marcha')
        ->assertSee('Obligatorio desde el 1 de enero de 2027')
        ->assertSee('Faltan 54 días')
        ->assertSee('Falta poco para que todos puedan marcar')
        ->assertSee('Centros listos')
        ->assertSee('Oficinas necesita zona y pantalla.')
        ->assertSee('Planta Norte necesita activar su pantalla QR.')
        ->assertSee('Ana López, Bob Díaz y Elena Vega no tienen horario')
        ->assertSee('Faltan 3 personas')
        ->assertSee('Hoy usas los valores de inicio')
        ->assertSee('Revisar política');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('takes RH from each step to the page where it is done', function (): void {
    $url = setupPanel();

    visit("{$url}/attendance/setup")
        ->click('Fijar 3 horarios')
        ->waitForText('Horarios')
        ->assertPathIs("{$url}/attendance/schedules")
        ->assertQueryStringHas('status', 'unscheduled')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
    visit("{$url}/attendance/setup")
        ->click('Terminar Oficinas')
        ->waitForText('Oficinas')
        ->assertSee('Este centro aún no tiene zonas')
        ->assertNoJavaScriptErrors();
});
