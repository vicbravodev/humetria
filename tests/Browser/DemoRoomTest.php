<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Support\Facades\Queue;

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
        ->assertSeeIn('#room-reset', 'Reiniciar demo')
        ->assertSee('Como dueño ves la empresa completa')
        ->assertDontSee('ya envejecieron');
})->with($looks);

it('says before a reset what it wipes, what keeps working and that the door closes', function (): void {
    signInAsSuperadmin();
    preparedDemo();

    visitPage('demos/room', '/admin/demo')
        ->click('button:has-text("Reiniciar demo")')
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

/** A script that resolves once the page shows `$text`, waiting up to 8 s for the room's poll (every 3 s). */
function roomShows(string $text): string
{
    $wanted = json_encode($text, JSON_THROW_ON_ERROR);

    return <<<JS
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                const seen = document.body.innerText.includes({$wanted});
                seen || performance.now() - start > 8000 ? resolve(seen) : setTimeout(check, 100);
            };
            check();
        })
        JS;
}

/** A session for Grupo Ejemplo of NOM-035 and Asistencia, on the prepared demo's center, as `$status`. */
function sessionInRoom(DemoSessionStatus $status): DemoSession
{
    $center = WorkCenter::query()->withoutGlobalScopes()->where('name', 'Oficinas Querétaro')->sole();

    return DemoSession::query()->forceCreate([
        'client_name' => 'Grupo Ejemplo',
        'tours' => ['nom035', 'attendance'],
        'skipped_steps' => [],
        'visited' => ['tenant.work-centers.index'],
        'status' => $status,
        'work_center_id' => $status === DemoSessionStatus::Active ? $center->id : null,
        'started_at' => now()->subMinutes(12),
        'ready_at' => $status === DemoSessionStatus::Active ? now()->subMinutes(12) : null,
        'ended_at' => $status === DemoSessionStatus::Failed ? now()->subMinutes(11) : null,
    ]);
}

it('starts a session with two tours, shows it preparing, and ends it', function (): void {
    Queue::fake();
    signInAsSuperadmin();
    preparedDemo();

    $page = visitPage('demos/room', '/admin/demo')
        ->assertSee('Nueva sesión')
        ->assertSee('El centro y su gente')
        ->assertSee('Empezar reinicia la demo de cero (unos 30 s).')
        ->assertButtonDisabled('Empezar sesión')
        ->assertButtonDisabled('button[aria-label="Subir NOM-035"]')
        ->assertButtonEnabled('button[aria-label="Bajar NOM-035"]')
        ->assertButtonDisabled('button[aria-label="Bajar Humi"]')
        ->fill('client_name', 'Grupo Ejemplo')
        ->click('#tour-attendance')
        ->click('#tour-nom035')
        ->click('button[aria-label="Subir Asistencia"]')
        ->assertSee('26 min')
        ->assertSee('3 recorridos')
        ->click('Empezar sesión')
        ->assertSee('Preparando la sesión')
        ->assertSee('Reiniciar la demo')
        ->assertSee('Crear el centro «Sala · Grupo Ejemplo» y dejar lista la sala')
        ->assertSee('Disponibles cuando la sesión esté lista.')
        ->assertNoJavaScriptErrors();

    $session = DemoSession::query()->sole();
    expect($session->tours)->toBe(['attendance', 'nom035'])->and($session->client_name)->toBe('Grupo Ejemplo');

    DemoRun::query()->where('kind', 'session')->sole()->finish();
    $session->markReady(WorkCenter::query()->withoutGlobalScopes()->where('name', 'Oficinas Querétaro')->sole()->id);

    $page->assertScript(roomShows('Entrar y seguir el guía'), true)
        ->click('Terminar sesión')
        ->assertSee('¿Terminar la sesión con Grupo Ejemplo?')
        ->assertSee('Se borra todo, incluidas las personas que diste de alta: sus correos, teléfonos, respuestas, marcas y llaves. La demo vuelve a su historia de siempre.')
        ->click('Terminar y borrar')
        ->assertSee('Terminando la sesión')
        ->assertNoJavaScriptErrors();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
});

it('shows the session in course: each tour\'s progress, the next step and the tools', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    preparedDemo();
    sessionInRoom(DemoSessionStatus::Active);

    visitPage('demos/room', '/admin/demo', $dark, $mobile)
        ->assertSee('Sesión en curso con Grupo Ejemplo · empezó hace 12 minutos')
        ->assertSee('Sesión con Grupo Ejemplo')
        ->assertSee('Da de alta al público')
        ->assertSee('El centro y su gente, paso 2 de 2')
        ->assertSee('1 de 2')
        ->assertSee('0 de 6')
        ->assertSee('Herramientas de la sala')
        ->assertSee('La puerta')
        ->assertSee('Como dueño o como RH. El guía aparece igual.')
        ->assertDontSee('Nueva sesión')
        ->assertDontSee('Reiniciar demo');
})->with($looks);

it('enters the demo and lands on the guide\'s step', function (): void {
    signInAsSuperadmin();
    $demo = preparedDemo();
    memberOf($demo, TenantRole::Owner);
    sessionInRoom(DemoSessionStatus::Active);

    visitPage('demos/room', '/admin/demo')
        ->click('Entrar y seguir el guía')
        ->assertPathIs('/admin/alba-demo/employees')
        ->assertNoJavaScriptErrors();
});

it('shows a failed session and lets it end', function (bool $mobile): void {
    signInAsSuperadmin();
    preparedDemo();
    sessionInRoom(DemoSessionStatus::Failed);
    DemoRun::query()->forceCreate(['kind' => 'session', 'status' => 'failed', 'step' => 'Sala de la sesión', 'error_class' => 'Illuminate\\Database\\QueryException', 'started_at' => now()->subMinutes(12), 'finished_at' => now()->subMinutes(11)]);

    visitPage('demos/room', '/admin/demo', mobile: $mobile)
        ->assertSee('La sesión con Grupo Ejemplo no quedó lista')
        ->assertSee('Falló')
        ->assertSee('Termina la sesión para dejar la demo limpia y empieza otra')
        ->assertDontSee('Nueva sesión')
        ->assertDontSee('Entrar y seguir el guía')
        ->assertDontSee('Herramientas de la sala')
        ->click('Terminar sesión')
        ->assertSee('¿Terminar la sesión con Grupo Ejemplo?');
})->with(['computer' => false, 'phone' => true]);
