<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with a center: Ana linked her phone (a synced key), Bob has no email and no device. */
function devicesPanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora', 'email' => 'ana@acme.test']);
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista', 'email' => null]);
    AttendanceDevice::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'label' => 'iPhone de Ana', 'backup_eligible' => true, 'backup_state' => true, 'last_used_at' => now()]);

    return $url;
}

it('shows RH each person with their devices and the kind of key', function (bool $dark, bool $mobile): void {
    $url = devicesPanel();

    visitPage('attendance/devices', "{$url}/attendance/devices", $dark, $mobile)
        ->assertSee('Dispositivos')
        ->assertSee('Ana López')
        ->assertSee('iPhone de Ana')
        ->assertSee('Llave sincronizada')
        ->assertSee('Bob Díaz')
        ->assertSee('Sin dispositivo');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('gives RH the link of a person without email once, to hand over', function (): void {
    $url = devicesPanel();

    visit("{$url}/attendance/devices")
        ->click('Crear enlace de alta')
        ->click('Crear enlace')
        ->waitForText('Enlace de alta de Bob Díaz')
        ->assertSee('Funciona una sola vez, vence en 48 h')
        ->assertScript('() => document.querySelector("#enrollment-link").value.includes("/a/acme/enroll/")', true)
        ->refresh()
        ->assertDontSee('Enlace de alta de Bob Díaz')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets RH revoke a lost phone after asking', function (): void {
    $url = devicesPanel();

    visit("{$url}/attendance/devices")
        ->click('Revocar')
        ->assertSee('¿Revocar «iPhone de Ana»?')
        ->click('Revocar dispositivo')
        ->waitForText('Dispositivo revocado')
        ->assertSee('Revocado')
        ->assertNoJavaScriptErrors();
});
