<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendanceZone;
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
        ->assertSee('Listo para marcar')
        ->assertSee('2 personas')
        ->assertSee('Nave 1')
        ->assertSee('80 m')
        ->assertSee('19.432712, -99.133298')
        ->assertSee('Agregar zona');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

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
