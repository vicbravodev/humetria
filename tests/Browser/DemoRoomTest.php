<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Tenancy\WorkCenterFactory;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** The demo company as the room sees it once prepared: its row, a screen at its offices and a run that finished. */
function preparedDemo(int $daysAgo = 1): Tenant
{
    $demo = Tenant::factory()->demo()->create(['trade_name' => 'Manufacturas Alba del Bajío', 'legal_name' => 'Manufacturas Alba del Bajío', 'slug' => 'alba-demo']);
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id, 'name' => 'Oficinas Querétaro']);
    KioskDevice::factory()->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id, 'activated_at' => now()]);
    DemoRun::query()->forceCreate(['kind' => 'prepare', 'status' => 'finished', 'started_at' => now()->subDays($daysAgo)->subMinutes(2), 'finished_at' => now()->subDays($daysAgo)]);

    return $demo;
}

it('offers to prepare the demo when there is none', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();

    visitPage('demos/room', '/admin/demo', $dark, $mobile)
        ->assertSee('Sala de demostración')
        ->assertSee('Sin preparar')
        ->assertSee('Aún no hay demo')
        ->assertSee('Preparar demo');
})->with($looks);

it('shows the prepared demo: enter it, its door, its complaint channel and its time-clock screen', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    preparedDemo();

    visitPage('demos/room', '/admin/demo', $dark, $mobile)
        ->assertSee('Preparada')
        ->assertSee('Manufacturas Alba del Bajío · preparada hace 1 día')
        ->assertSee('Entrar como dueño')
        ->assertSee('Entrar como RH')
        ->assertSee('La puerta')
        ->assertSee('Abrir sala (2 h)')
        ->assertSee('Cada persona se entrega una sola vez: al acabarse, la puerta dice «La sala está llena» hasta que reinicies la demo.')
        ->assertSee('Canal de denuncias')
        ->assertSee('Pantalla del checador')
        ->assertSee('Antes de presentar')
        ->assertSee('Reiniciar demo')
        ->assertDontSee('ya envejecieron');
})->with($looks);

it('warns that a demo older than two days has gone stale', function (): void {
    signInAsSuperadmin();
    preparedDemo(daysAgo: 3);

    visitPage('demos/room', '/admin/demo')
        ->assertSee('La demo se preparó hace 3 días.')
        ->assertSee('reiníciala antes de presentar');
});

it('shows the step a preparation is on and holds the buttons meanwhile', function (bool $dark): void {
    signInAsSuperadmin();
    (DemoRun::start('prepare') ?? throw new LogicException('Not started.'))->step('Asistencia');

    visitPage('demos/room', '/admin/demo', $dark)
        ->assertSee('Preparando')
        ->assertSee('Preparando: Asistencia.')
        ->assertButtonDisabled('Preparar demo');
})->with(['light' => false, 'dark' => true]);

it('issues the link of the time-clock screen once, with its QR', function (): void {
    signInAsSuperadmin();
    preparedDemo();

    visitPage('demos/room', '/admin/demo', mobile: true)
        ->click('Emitir enlace de la pantalla')
        ->assertSee('funciona una sola vez')
        ->assertSee('/a/alba-demo/kiosk/')
        ->assertPresent('img[alt="Código QR del enlace de la pantalla del checador"]')
        ->assertNoJavaScriptErrors();
});
