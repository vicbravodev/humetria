<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Organization\Models\Employee;
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
        ->assertSee('Pantallas del checador')
        ->assertSee('cada persona marca con la de su centro')
        ->assertSee('Antes de presentar')
        ->assertSee('pendientes se escalan (a los tres días hábiles) y la encuesta de pulso cierra (a los nueve días)')
        ->assertSee('revisa el buzón antes de presentar')
        ->assertSee('Reiniciar demo')
        ->assertDontSee('ya envejecieron');
})->with($looks);

it('enters the demo with no countdown in the strip: it lasts until «Salir»', function (): void {
    signInAsSuperadmin();
    ownerOf(preparedDemo())->forceFill(['name' => 'Laura Alba'])->save();

    visit('/admin/demo')
        ->click('Entrar como dueño')
        ->assertSee('Estás viendo Humetría como Laura Alba · Salir')
        ->assertDontSee('Quedan')
        ->assertNoJavaScriptErrors();
});

it('says before a reset what it wipes, what keeps working and that the door closes', function (): void {
    signInAsSuperadmin();
    preparedDemo();

    visitPage('demos/room', '/admin/demo')
        ->click('Reiniciar demo')
        ->assertSee('El QR del canal de denuncias sigue sirviendo.')
        ->assertSee('La puerta se cierra: ábrela otra vez; los enlaces que dio dejan de servir.')
        ->assertSee('Tarda alrededor de medio minuto');
});

it('warns that a demo older than two days has gone stale', function (): void {
    signInAsSuperadmin();
    preparedDemo(daysAgo: 3);

    visitPage('demos/room', '/admin/demo')
        ->assertSee('La demo se preparó hace 3 días.')
        ->assertSee('reiníciala antes de presentar');
});

it('shows the step a preparation is on and holds the buttons meanwhile', function (bool $dark): void {
    signInAsSuperadmin();
    (DemoRun::start('prepare') ?? throw new LogicException('Not started.'))->reach('Asistencia');

    visitPage('demos/room', '/admin/demo', $dark)
        ->assertSee('Preparando')
        ->assertSee('Preparando: Asistencia.')
        ->assertButtonDisabled('Preparar demo');
})->with(['light' => false, 'dark' => true]);

it('does not let anyone into a demo while a reset is rebuilding it', function (bool $dark): void {
    signInAsSuperadmin();
    preparedDemo();
    (DemoRun::start('reset') ?? throw new LogicException('Not started.'))->reach('Asistencia');

    visitPage('demos/room', '/admin/demo', $dark)
        ->assertSee('Preparando: Asistencia.')
        ->assertButtonDisabled('Entrar como dueño')
        ->assertButtonDisabled('Entrar como RH');
})->with(['light' => false, 'dark' => true]);

it('brings the door up to date when the preparation it was waiting on ends', function (): void {
    signInAsSuperadmin();
    // The audience's workers are the demo's employees: none until the preparation makes them.
    app()->instance(DemoDoorPool::class, new class implements DemoDoorPool
    {
        public function employeeIds(): array
        {
            return Employee::query()->withoutGlobalScopes()->pluck('id')->all();
        }
    });
    $run = DemoRun::start('prepare') ?? throw new LogicException('Not started.');

    $page = visitPage('demos/room', '/admin/demo')->assertSee('Preparando');

    $demo = preparedDemo();
    Employee::factory()->count(3)->create(['tenant_id' => $demo->id]);
    $run->finish();

    $page->assertSee('Quedan 3 de 3 libres')
        ->assertNoJavaScriptErrors();
});

it('issues the links of the time-clock screens once, each by its center with its QR', function (): void {
    signInAsSuperadmin();
    preparedDemo();

    visitPage('demos/room', '/admin/demo', mobile: true)
        ->click('Emitir enlaces de las pantallas')
        ->assertSee('funciona una sola vez')
        ->assertSee('/a/alba-demo/kiosk/')
        ->assertPresent('img[alt="Código QR del enlace de la pantalla del checador de Oficinas Querétaro"]')
        ->assertNoJavaScriptErrors();
});
