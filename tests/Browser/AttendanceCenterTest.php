<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

/** RH's panel with Planta Norte, two people working there and, when asked, its Nave 1 zone. */
function centerPanel(bool $withZone = true, TenantRole $role = TenantRole::Owner): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    TenantMembership::factory()->for($tenant)->for($user)->role($role)->create();
    test()->actingAs($user);
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte', 'main_activity' => 'Manufactura de autopartes']);
    EmployeeFactory::new()->count(2)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);

    if ($withZone) {
        AttendanceZone::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Nave 1', 'latitude' => 19.432712, 'longitude' => -99.133298, 'radius_m' => 80]);
    }

    return ["/admin/acme/attendance/centers/{$plant->id}", $plant];
}

it('shows RH a center with its zones and where each is', function (bool $dark, bool $mobile): void {
    [$url] = centerPanel();

    visitPage('attendance/center', $url, $dark, $mobile)
        ->assertSee('Planta Norte')
        ->assertSee('Falta pantalla QR')
        ->assertSee('0 pantallas activas')
        ->assertSee('2 personas')
        ->assertSee('Nave 1')
        ->assertSee('80 m')
        ->assertSee('19.432712, -99.133298')
        ->assertSee('Agregar zona')
        ->assertSee('Menor que la precisión');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('lets RH touch the plan of the zones to see whether a mark there would count', function (): void {
    [$url] = centerPanel();

    // The plan's center is the center of its only zone; its corner is far outside it.
    visit($url)
        ->assertSee('Toca cualquier punto del plano')
        ->click('svg[aria-label^="Plano de"]')
        ->waitForText('Dentro de Nave 1')
        ->assertSee('Aquí una marca se acepta')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows a viewer no plan, since where the zones are is for whoever may change them', function (): void {
    [$url] = centerPanel(role: TenantRole::Viewer);

    visit($url)->assertSee('Nave 1')->assertDontSee('Toca cualquier punto del plano');
});

it('flags a center without zones with the next step', function (bool $dark): void {
    [$url] = centerPanel(withZone: false);

    visitPage('attendance/center', $url, $dark)
        ->assertSee('Sin zonas')
        ->assertSee('Este centro aún no tiene zonas')
        ->assertSee('Agregar zona');
})->with(['light' => [false], 'dark' => [true]]);

it('shows a viewer the zones without where they are, nor a way to change them', function (): void {
    [$url] = centerPanel(role: TenantRole::Viewer);

    visitPage('attendance/center', $url)
        ->assertSee('Nave 1')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertDontSee('19.432712')
        ->assertDontSee('Agregar zona')
        ->assertDontSee('Cambiar');
});

it('lets RH add a zone where they stand', function (): void {
    [$url, $plant] = centerPanel(withZone: false);
    $page = visit($url)->assertSee('Planta Norte');
    standingAt($page, 19.4401, -99.2011, 12);

    $page->click('Agregar zona')
        ->fill('name', 'Patio de carga')
        ->click('Usar mi ubicación actual')
        ->waitForText('Tu ubicación tiene una precisión de ±12 m.')
        ->assertValue('latitude', '19.440100')
        ->assertValue('longitude', '-99.201100')
        ->fill('radius_m', '120')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Zona guardada')
        ->assertSee('Patio de carga')
        ->assertSee('120 m')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    expect(AttendanceZone::query()->sole())->work_center_id->toBe($plant->id)->latitude->toBe(19.4401)->radius_m->toBe(120);
});

it('tells RH why the browser gave no location, keeping the fields to type', function (): void {
    [$url] = centerPanel(withZone: false);

    visit($url)
        ->click('Agregar zona')
        ->click('Usar mi ubicación actual')
        ->waitForText('Tu navegador no dio permiso de usar tu ubicación')
        ->fill('name', 'Oficina')
        ->fill('latitude', '19.5')
        ->fill('longitude', '-99.1')
        ->fill('radius_m', '5')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('El campo radio debe estar entre 10 y 5000.')
        ->assertNoJavaScriptErrors();

    expect(AttendanceZone::query()->count())->toBe(0);
});

it('lets RH change a zone', function (): void {
    [$url] = centerPanel();

    visit($url)
        ->click('[aria-label="Cambiar Nave 1"]')
        ->assertValue('latitude', '19.432712')
        ->fill('name', 'Nave 1 y patio')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Zona guardada')
        ->assertSee('Nave 1 y patio')
        ->assertNoJavaScriptErrors();

    expect(AttendanceZone::query()->sole()->name)->toBe('Nave 1 y patio');
});

it('reads where RH stands on a center opened from the overview, whose own document allows it', function (): void {
    centerPanel(withZone: false);
    $page = visit('/admin/acme/attendance')->assertSee('Centros y zonas');
    standingAt($page, 19.4401, -99.2011, 8);

    $page->click('[aria-label="Zonas y pantallas de Planta Norte"]')
        ->waitForText('Este centro aún no tiene zonas')
        ->click('Agregar zona')
        ->click('Usar mi ubicación actual')
        ->waitForText('Tu ubicación tiene una precisión de ±8 m.')
        ->assertValue('latitude', '19.440100')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('lets RH issue a screen, showing its link once to copy, and revoke it', function (bool $dark): void {
    [$url, $plant] = centerPanel();

    $page = visitPage('attendance/center', $url, $dark)
        ->assertSee('Sin pantallas del QR')
        ->click('Emitir pantalla')
        ->waitForText('Enlace de la pantalla')
        ->assertSee('funciona una sola vez')
        ->assertSee('Emitida, sin activar')
        ->assertSee('Aún no muestra el código')
        ->assertNoAccessibilityIssues(1);
    expect(KioskDevice::query()->sole()->work_center_id)->toBe($plant->id);

    $page->navigate($url)
        ->assertDontSee('Enlace de la pantalla')
        ->click('Volver a emitir')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Enlace de la pantalla')
        ->navigate($url)
        ->click('Revocar')
        ->click('[role="dialog"] button[type="submit"]')
        ->waitForText('Pantalla revocada')
        ->assertSee('Revocada el')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
    expect(KioskDevice::query()->sole()->revoked_at)->not->toBeNull();
})->with(['light' => [false], 'dark' => [true]]);

it('shows the screens of a center in use, with when each was last seen', function (bool $mobile): void {
    [$url, $plant] = centerPanel();
    KioskDevice::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'activated_at' => now(), 'last_seen_at' => now()]);

    visitPage('attendance/center', $url, mobile: $mobile)
        ->assertSee('Activa')
        ->assertSee('Vista el')
        ->assertSee('Revocar')
        ->assertDontSee('Volver a emitir');
})->with(['desktop' => [false], 'phone' => [true]]);

it('warns before revoking the only screen that shows the code, and not when another does', function (): void {
    [$url, $plant] = centerPanel();
    $screen = fn () => KioskDevice::factory()->create(['tenant_id' => $plant->tenant_id, 'work_center_id' => $plant->id, 'activated_at' => now(), 'last_seen_at' => now()]);
    $screen();

    visit($url)
        ->click('Revocar')
        ->assertSee('Es la única pantalla activa de este centro')
        ->assertSee('nadie podrá marcar con el QR en el sitio hasta que active otra');

    $screen();

    visit($url)
        ->click('Revocar')
        ->assertSee('Dejará de mostrar el código de inmediato.')
        ->assertDontSee('Es la única pantalla activa de este centro');
});
