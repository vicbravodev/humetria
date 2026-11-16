<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with a center: Ana created her key (a synced one), Bob has no email and no key. */
function accessPanel(): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'job_title' => 'Operadora', 'email' => 'ana@acme.test']);
    EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Bob Díaz', 'job_title' => 'Almacenista', 'email' => null]);
    WorkerCredential::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'label' => 'iPhone de Ana', 'backup_eligible' => true, 'backup_state' => true, 'last_used_at' => now()]);

    return $url;
}

/** A script that reads the status chip of a person's row, not the tab labels that carry the same words. */
function accessChipOf(string $name): string
{
    return "() => [...([...document.querySelectorAll('tbody tr')].find((row) => row.textContent.includes('{$name}'))?.querySelectorAll('[data-slot=\"badge\"]') ?? [])].at(-1)?.textContent ?? null";
}

it('shows RH each person with their keys and the kind of key', function (bool $dark, bool $mobile): void {
    $url = accessPanel();

    visitPage('worker-access/access', "{$url}/access", $dark, $mobile)
        ->assertSee('Accesos')
        ->assertSee('Ana López')
        ->assertSee('iPhone de Ana')
        ->assertSee('Llave sincronizada')
        ->assertSee('Bob Díaz')
        ->assertScript(accessChipOf('Bob Díaz'), 'Sin invitar')
        ->assertScript(accessChipOf('Ana López'), 'Con llave');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('gives RH the link of a person without email once, to hand over, and shows them invited', function (): void {
    $url = accessPanel();

    visit("{$url}/access")
        ->assertSee('Aún no se le invita')
        ->assertSee('Sin correo no podemos enviarle la invitación: crea su enlace y entrégaselo.')
        ->click('tr:has-text("Bob Díaz") >> text=Crear enlace')
        ->press('[role="dialog"] button[type="submit"]')
        ->waitForText('Enlace de acceso de Bob Díaz')
        ->assertSee('Funciona una sola vez, vence en 48 h')
        ->assertScript('() => document.querySelector("#access-link").value.includes("/me/acme/link/")', true)
        ->assertSee('Esperando que abra su invitación.')
        ->assertScript(accessChipOf('Bob Díaz'), 'Invitada')
        ->refresh()
        ->assertDontSee('Enlace de acceso de Bob Díaz')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets RH revoke one key after asking', function (): void {
    $url = accessPanel();

    visit("{$url}/access")
        ->click('Revocar')
        ->assertSee('¿Revocar «iPhone de Ana»?')
        ->assertSee('Las marcas que ya hizo se conservan')
        ->click('Revocar llave')
        ->waitForText('Llave revocada')
        ->assertScript(accessChipOf('Ana López'), 'Sin invitar')
        ->assertNoJavaScriptErrors();

    expect(WorkerCredential::query()->withoutGlobalScopes()->sole()->revoked_at)->not->toBeNull();
});

it('reissues the access of a person who lost their phone (CUJ 7)', function (): void {
    $url = accessPanel();

    visit("{$url}/access")
        ->click('Reemitir acceso')
        ->assertSee('¿Reemitir el acceso de Ana López?')
        ->assertSee('Todas las llaves de Ana López dejan de servir')
        ->press('[role="dialog"] button[type="submit"]')
        ->waitForText('Reemitimos el acceso de Ana López')
        ->assertScript(accessChipOf('Ana López'), 'Invitada')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(WorkerCredential::query()->withoutGlobalScopes()->sole()->revoked_reason)->toBe('reissued');
});

it('invites everyone of a center without a key, leaving who has no email to RH', function (): void {
    $url = accessPanel();
    $plant = WorkCenter::query()->where('name', 'Planta Norte')->sole();
    EmployeeFactory::new()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'name' => 'Carla Ruiz', 'email' => 'carla@acme.test']);

    visit("{$url}/access?center={$plant->id}")
        ->click('Invitar a todos los que no tienen llave')
        ->assertSee('¿Invitar a las 2 personas de Planta Norte sin llave?')
        ->click('Enviar invitaciones')
        ->waitForText('Enviamos 1 invitación. 1 persona no tiene correo')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('tells a viewer the accesses are theirs to see and not to change', function (): void {
    $url = accessPanel();
    $this->actingAs(memberOf(Tenant::query()->where('slug', 'acme')->sole(), TenantRole::Viewer));

    visitPage('worker-access/access', "{$url}/access")
        ->assertSee('Ana López')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertDontSee('Revocar');
});

it('takes RH from the sidebar to Accesos, lighting it', function (): void {
    $url = accessPanel();

    visit($url)
        ->click('[data-sidebar="sidebar"] >> text=Accesos')
        ->waitForText('Invítala para que cree la suya')
        ->assertPathIs("{$url}/access")
        ->assertAttribute('[data-sidebar="sidebar"] a[href$="/access"]', 'data-active', 'true')
        ->assertNoJavaScriptErrors();
});
