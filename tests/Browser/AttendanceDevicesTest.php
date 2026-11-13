<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Tenancy\Enums\TenantRole;
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

/** A script that reads the status chip of a person's row, not the tab labels that carry the same words. */
function deviceChipOf(string $name): string
{
    return "() => [...([...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes('{$name}'))?.querySelectorAll('[data-slot=\"badge\"]') ?? [])].at(-1)?.textContent ?? null";
}

it('shows RH each person with their devices and the kind of key', function (bool $dark, bool $mobile): void {
    $url = devicesPanel();

    visitPage('attendance/devices', "{$url}/attendance/devices", $dark, $mobile)
        ->assertSee('Dispositivos')
        ->assertSee('Ana López')
        ->assertSee('iPhone de Ana')
        ->assertSee('Llave sincronizada')
        ->assertSee('Bob Díaz')
        ->assertScript(deviceChipOf('Bob Díaz'), 'Sin dispositivo')
        ->assertScript(deviceChipOf('Ana López'), 'Con dispositivo');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('shows RH the road to the phone: who has no link, who was sent one and who already has a phone', function (): void {
    $url = devicesPanel();

    visit("{$url}/attendance/devices")
        ->assertSee('Avance del alta')
        ->assertSee('Sin enlace')
        ->assertSee('Bob')
        ->assertSee('Con teléfono')
        ->assertSee('Aún no se le envía el enlace')
        ->assertSee('Sin correo no podemos enviarle el enlace: crea el suyo y entrégaselo.')
        ->assertSee('Vinculada el')
        ->click('Crear enlace de alta')
        ->click('Crear enlace')
        ->waitForText('Enlace de alta de Bob Díaz')
        ->assertSee('Enviado el')
        ->assertSee('vence en 48 h')
        ->assertSee('Esperando que abra el enlace.')
        ->assertScript('() => document.querySelector("section[aria-labelledby=\'device-pipeline\']").textContent.includes("Enlace enviado")', true)
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

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
        ->assertSee('Las marcas que ya hizo se conservan')
        ->assertSee('Para volver a marcar tendrá que vincular un teléfono otra vez')
        ->click('Revocar dispositivo')
        ->waitForText('Dispositivo revocado')
        ->assertScript(deviceChipOf('Ana López'), 'Revocado')
        ->assertNoJavaScriptErrors();

    expect(AttendanceDevice::query()->withoutGlobalScopes()->sole()->revoked_at)->not->toBeNull();
});

it('sends their link to everyone of a center without a device, leaving who has no email to RH', function (): void {
    $url = devicesPanel();
    $plant = WorkCenter::query()->where('name', 'Planta Norte')->sole();
    EmployeeFactory::new()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'name' => 'Carla Ruiz', 'email' => 'carla@acme.test']);

    visit("{$url}/attendance/devices?center={$plant->id}")
        ->click('Enviar enlaces a todos los que no tienen dispositivo')
        ->assertSee('¿Enviar su enlace de alta a las 2 personas de Planta Norte sin dispositivo?')
        ->assertSee('Hoy nadie puede marcar en Planta Norte: le falta una zona o una pantalla.')
        ->assertSee('Configurar el centro')
        ->click('Enviar enlaces')
        ->waitForText('Enviamos 1 enlace de alta. 1 persona no tiene correo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('warns, in the link of a single person, that the center cannot mark yet, as the one for everybody does', function (): void {
    $url = devicesPanel();
    $plant = WorkCenter::query()->where('name', 'Planta Norte')->sole();
    EmployeeFactory::new()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'name' => 'Carla Ruiz', 'email' => 'carla@acme.test']);

    visit("{$url}/attendance/devices?center={$plant->id}")
        ->click('[aria-label="Enviar enlace de alta a Carla Ruiz"], tr:has-text("Carla Ruiz") >> text=Enviar enlace de alta')
        ->assertSee('¿Enviar el enlace de alta a Carla Ruiz?')
        ->assertSee('Hoy nadie puede marcar en Planta Norte: le falta una zona o una pantalla.')
        ->assertSee('Configurar el centro')
        ->assertNoJavaScriptErrors();
});

it('tells a viewer the devices are theirs to see and not to change', function (): void {
    $url = devicesPanel();
    $this->actingAs(memberOf(Tenant::query()->where('slug', 'acme')->sole(), TenantRole::Viewer));

    visitPage('attendance/devices', "{$url}/attendance/devices")
        ->assertSee('Ana López')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertDontSee('Revocar');
});
