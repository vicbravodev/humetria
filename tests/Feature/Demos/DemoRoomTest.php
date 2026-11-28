<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Actions\StartDemoRun;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Jobs\PrepareDemo;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\DemoLeadRegistry;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonInterface;
use Database\Factories\Tenancy\WorkCenterFactory;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

mutates(StartDemoRun::class, DemoLeadRegistry::class);

/*
 * La sala de demostración: the console page from which the staff prepares the demo company, enters it as its owner or
 * its HR, and shares it live (the complaint channel, the time-clock screen, the door). Nothing of a person reaches it.
 */

/** A door that remembers what the room asked of it; open or closed as told. */
function fakeDoor(bool $open = false): ArrayObject
{
    $calls = new ArrayObject;
    app()->instance(DoorControl::class, new readonly class($calls, $open) implements DoorControl
    {
        /** @param ArrayObject<int, string> $calls */
        public function __construct(private ArrayObject $calls, private bool $open) {}

        public function open(int $minutes): array
        {
            $this->calls->append("open {$minutes}");

            return ['url' => 'https://humetria.test/demo/CODIGO'];
        }

        public function close(): void
        {
            $this->calls->append('close');
        }

        public function state(): array
        {
            return ['open' => $this->open, 'expires_at' => $this->open ? '2026-11-16T20:30:00+00:00' : null, 'claimed' => 12, 'free' => 28, 'total' => 40];
        }
    });

    return $calls;
}

function demoCompany(): Tenant
{
    return Tenant::factory()->demo()->create(['trade_name' => 'Manufacturas Alba del Bajío', 'slug' => 'alba-demo']);
}

function finishedRun(CarbonInterface $at): DemoRun
{
    return DemoRun::query()->forceCreate(['kind' => 'prepare', 'status' => 'finished', 'started_at' => $at->subMinute(), 'finished_at' => $at]);
}

beforeEach(function (): void {
    $this->door = fakeDoor();
});

it('lives at /admin/demo', function (): void {
    expect(route('demo-room.show', absolute: false))->toBe('/admin/demo')
        ->and(route('demo-room.prepare', absolute: false))->toBe('/admin/demo/prepare')
        ->and(route('demo-room.reset', absolute: false))->toBe('/admin/demo/reset')
        ->and(route('demo-room.door.open', absolute: false))->toBe('/admin/demo/door')
        ->and(route('demo-room.door.close', absolute: false))->toBe('/admin/demo/door')
        ->and(route('demo-room.kiosk.reissue', absolute: false))->toBe('/admin/demo/kiosk');
});

$routes = [
    'the room' => ['get', 'demo-room.show'],
    'prepare' => ['post', 'demo-room.prepare'],
    'reset' => ['post', 'demo-room.reset'],
    'open the door' => ['post', 'demo-room.door.open'],
    'close the door' => ['delete', 'demo-room.door.close'],
    'reissue the screen' => ['post', 'demo-room.kiosk.reissue'],
];

it('sends guests to the login', function (string $method, string $route): void {
    $this->{$method}(route($route))->assertRedirect(route('login'));
})->with($routes);

it('answers 404 to whoever is not on the staff, before anything runs', function (string $method, string $route): void {
    Queue::fake();
    demoCompany();

    $this->actingAs(User::factory()->create())->{$method}(route($route))->assertNotFound()->assertSessionHasNoErrors();

    expect(DemoRun::query()->count())->toBe(0)->and($this->door->getArrayCopy())->toBe([]);
    Queue::assertNothingPushed();
})->with($routes);

it('answers 404 to support too: the room is the superadmin\'s alone', function (string $method, string $route): void {
    Queue::fake();
    demoCompany();

    $this->actingAs(support())->{$method}(route($route))->assertNotFound()->assertSessionHasNoErrors();

    expect(DemoRun::query()->count())->toBe(0)->and($this->door->getArrayCopy())->toBe([]);
    Queue::assertNothingPushed();
})->with($routes);

it('offers to prepare the demo when there is none', function (): void {
    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('demos/room')
            ->where('company', null)
            ->where('run', null)
            ->where('prepared', null)
            ->where('complaints', null)
            ->where('door', ['open' => false, 'closes_at' => null, 'claimed' => 12, 'free' => 28, 'total' => 40]));
});

it('shows the demo company to enter as its owner or its HR, how old it is and its complaint channel with its QR', function (): void {
    $this->freezeSecond();
    $demo = demoCompany();
    finishedRun(now()->subDay());

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company', ['id' => $demo->id, 'name' => 'Manufacturas Alba del Bajío'])
            ->where('run.status', 'finished')
            ->where('prepared.ago', 'hace 1 día')
            ->where('prepared.stale', false)
            ->where('complaints.url', route('complaints.public.show', ComplaintsStory::accessCode()))
            ->where('complaints.qr', fn (string $qr): bool => str_starts_with($qr, 'data:image/svg+xml;base64,')));
});

it('warns that the demo goes stale once it is more than two days old', function (int $minutes, bool $stale): void {
    $this->freezeSecond();
    demoCompany();
    finishedRun(now()->subDays(2)->subMinutes($minutes));

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('prepared.stale', $stale));
})->with([
    'exactly two days' => [0, false],
    'two days and a minute' => [1, true],
]);

it('dates the preparation by the last run that finished, not by one running or failed after it', function (): void {
    $this->freezeSecond();
    demoCompany();
    finishedRun(now()->subDays(3));
    DemoRun::query()->forceCreate(['kind' => 'reset', 'status' => 'failed', 'step' => 'NOM-035', 'error_class' => RuntimeException::class, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('prepared.ago', 'hace 3 días')
            ->where('run', ['kind' => 'reset', 'status' => 'failed', 'step' => 'NOM-035', 'error_class' => RuntimeException::class]));
});

it('shows the step a running preparation is on', function (): void {
    (DemoRun::start('prepare') ?? throw new LogicException('Not started.'))->reach('Asistencia');

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('run', ['kind' => 'prepare', 'status' => 'running', 'step' => 'Asistencia', 'error_class' => null]));
});

it('offers the tours to choose, in the catalog\'s order, without «El centro y su gente» that always goes first', function (): void {
    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session', null)
            ->where('tours', [
                ['key' => 'nom035', 'title' => 'NOM-035', 'summary' => 'Crea la evaluación, el público contesta en su teléfono y ves el informe al cerrar.', 'minutes' => 12],
                ['key' => 'attendance', 'title' => 'Asistencia', 'summary' => 'Modo del centro, zona y kiosco; el público crea su llave y marca.', 'minutes' => 10],
                ['key' => 'engagement', 'title' => 'Clima y pulso', 'summary' => 'Encuesta de clima del centro; las respuestas llegan en vivo.', 'minutes' => 8],
                ['key' => 'complaints', 'title' => 'Denuncias', 'summary' => 'Alguien denuncia con el QR y RH la recibe y la atiende.', 'minutes' => 5],
                ['key' => 'leave', 'title' => 'Vacaciones', 'summary' => 'Abres el portal de una persona, pide días y los apruebas como RH.', 'minutes' => 6],
                ['key' => 'humi', 'title' => 'Humi', 'summary' => 'Una pregunta sobre el cumplimiento de la empresa.', 'minutes' => 3],
            ]));
});

/** A session of the demo, for Grupo Ejemplo, as `$status`. */
function roomSession(DemoSessionStatus $status, ?string $workCenterId = null): DemoSession
{
    return DemoSession::query()->forceCreate([
        'client_name' => 'Grupo Ejemplo',
        'tours' => ['nom035', 'attendance'],
        'skipped_steps' => [],
        'status' => $status,
        'work_center_id' => $workCenterId,
        'started_at' => now()->subMinutes(12),
        'ready_at' => $status === DemoSessionStatus::Active ? now()->subMinutes(12) : null,
        'ended_at' => in_array($status, [DemoSessionStatus::Ended, DemoSessionStatus::Failed], true) ? now()->subMinutes(10) : null,
    ]);
}

it('lists the tours and the open session', function (): void {
    $this->freezeSecond();
    $demo = demoCompany();
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id]);
    roomSession(DemoSessionStatus::Active, $center->id)->forceFill(['visited' => ['tenant.work-centers.index'], 'skipped_steps' => ['nom035.draft']])->save();

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.client', 'Grupo Ejemplo')
            ->where('session.status', 'active')
            ->where('session.failed', false)
            ->where('session.since', 'hace 12 minutos')
            ->where('session.tours', [
                ['key' => 'base', 'title' => 'El centro y su gente', 'done' => 1, 'total' => 2],
                ['key' => 'nom035', 'title' => 'NOM-035', 'done' => 1, 'total' => 6],
                ['key' => 'attendance', 'title' => 'Asistencia', 'done' => 0, 'total' => 7],
            ])
            ->where('session.next', ['title' => 'Da de alta al público', 'tour' => 'El centro y su gente', 'index' => 2, 'total' => 2, 'href' => '/admin/alba-demo/employees']));

    // The room reads the demo's data from inside it, and only for that: the console goes on outside the demo.
    expect(Context::has('tenant_id'))->toBeFalse()
        ->and(Context::hasHidden(TenantContext::DEMO))->toBeFalse()
        ->and(resolve(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('shows the humi step done once anyone asked Humi in the demo, though the room is the superadmin\'s', function (): void {
    $demo = demoCompany();
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id]);
    roomSession(DemoSessionStatus::Active, $center->id)->forceFill(['tours' => ['humi']])->save();
    $owner = memberOf($demo, TenantRole::Owner);
    $participant = ConversationParticipant::factory()->for($owner)->create(['tenant_id' => $demo->id]);
    AiUsage::factory()->create(['tenant_id' => $demo->id, 'participant_id' => $participant->id]);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('session.tours.1', ['key' => 'humi', 'title' => 'Humi', 'done' => 1, 'total' => 1]));
});

it('shows a session being prepared or ending with its tours and nothing done yet', function (DemoSessionStatus $status): void {
    roomSession($status);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.status', $status->value)
            ->where('session.failed', false)
            ->where('session.next', null)
            ->where('session.tours.1', ['key' => 'nom035', 'title' => 'NOM-035', 'done' => 0, 'total' => 6]));
})->with([DemoSessionStatus::Preparing, DemoSessionStatus::Ending]);

it('shows a failed session with a way to end it', function (): void {
    roomSession(DemoSessionStatus::Failed);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.client', 'Grupo Ejemplo')
            ->where('session.status', 'failed')
            ->where('session.failed', true)
            ->where('session.next', null));
});

it('forgets a session once it ended, and a failed one once a newer session ended after it', function (): void {
    roomSession(DemoSessionStatus::Failed)->forceFill(['started_at' => now()->subHour()])->save();
    roomSession(DemoSessionStatus::Ended);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('session', null));
});

it('shows the door open, until when, and how many workers are left for the audience', function (): void {
    fakeDoor(open: true);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('door', ['open' => true, 'closes_at' => '14:30', 'claimed' => 12, 'free' => 28, 'total' => 40]));
});

it('reads the page in a fixed number of queries', function (): void {
    demoCompany();
    finishedRun(now()->subDay());
    $this->actingAs(superadmin());

    // One of them reads the last demo session.
    $this->expectsDatabaseQueryCount(10);

    $this->get(route('demo-room.show'))->assertOk();
});

it('carries no person of the demo: no answer, no name, no email', function (): void {
    $demo = demoCompany();
    finishedRun(now()->subDay());
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id]);
    workIn($demo);
    $worker = Employee::factory()->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id, 'name' => 'Rosaura Ibarra', 'email' => 'rosaura@demo.humetria.test']);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(function (Assert $page) use ($worker): void {
            $props = $page->toArray()['props'];
            expectNoIndividualResponses($props);
            expect(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('Rosaura')->not->toContain('demo.humetria.test')->not->toContain($worker->id);
        });
});

it('prepares the demo on the queue, once at a time', function (string $route, string $kind, string $job): void {
    Queue::fake();
    $this->actingAs(superadmin());

    $this->post(route($route))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');
    $this->post(route($route))->assertRedirect()->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Ya hay una preparación corriendo: espera a que termine.']);

    $run = DemoRun::query()->sole();
    expect($run->kind)->toBe($kind)->and($run->status)->toBe('running');
    Queue::assertPushed($job, 1);
    Queue::assertPushed($job, fn (PrepareDemo|ResetDemo $pushed): bool => $pushed->runId === $run->id);
})->with([
    'prepare' => ['demo-room.prepare', 'prepare', PrepareDemo::class],
    'reset' => ['demo-room.reset', 'reset', ResetDemo::class],
]);

it('neither prepares nor resets the demo under an open session: ending it is what wipes it', function (string $route, string $message, DemoSessionStatus $status): void {
    Queue::fake();
    $demo = demoCompany();
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id]);
    $session = roomSession($status, $center->id);

    $this->actingAs(superadmin())
        ->post(route($route))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => $message]);

    expect(DemoRun::query()->count())->toBe(0)
        ->and($session->refresh()->status)->toBe($status)
        ->and($session->work_center_id)->toBe($center->id);
    Queue::assertNothingPushed();
})->with([
    'prepare' => ['demo-room.prepare', 'Hay una sesión en curso: termínala antes de preparar la demo.'],
    'reset' => ['demo-room.reset', 'Hay una sesión en curso: termínala para reiniciar la demo.'],
])->with([DemoSessionStatus::Preparing, DemoSessionStatus::Active, DemoSessionStatus::Ending]);

it('records in the audit trail who started a run, with ids only', function (string $route, string $event): void {
    Queue::fake();
    $demo = demoCompany();
    $admin = superadmin();

    $this->actingAs($admin)->post(route($route))->assertRedirect();

    $entry = DB::table('audit_entries')->where('event', $event)->sole();
    expect($entry->tenant_id)->toBe($demo->id)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe(DemoRun::query()->sole()->id);
})->with([
    'prepare' => ['demo-room.prepare', 'demo.prepare_started'],
    'reset' => ['demo-room.reset', 'demo.reset_started'],
]);

it('records the very first preparation too, before the demo company exists', function (): void {
    Queue::fake();

    $this->actingAs(superadmin())->post(route('demo-room.prepare'))->assertRedirect();

    expect(DB::table('audit_entries')->where('event', 'demo.prepare_started')->sole()->tenant_id)->toBeNull();
});

it('fails the run and frees the lock when the queue cannot take the job', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused [redis-long]'));

    $this->actingAs(superadmin())
        ->post(route('demo-room.prepare'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No se pudo encolar la preparación: inténtalo de nuevo en unos minutos.']);

    $run = DemoRun::query()->sole();
    expect($run->status)->toBe('failed')
        ->and($run->error_class)->toBe(RuntimeException::class)
        ->and(DemoRun::start('prepare'))->toBeInstanceOf(DemoRun::class);
});

it('opens the door for two hours and shows its link and QR this once', function (): void {
    $this->actingAs(superadmin())
        ->post(route('demo-room.door.open'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.doorLink.url', 'https://humetria.test/demo/CODIGO');

    expect(session('inertia.flash_data.doorLink.qr'))->toStartWith('data:image/svg+xml;base64,')
        ->and($this->door->getArrayCopy())->toBe(['open 120']);
});

it('closes the door', function (): void {
    $this->actingAs(superadmin())->delete(route('demo-room.door.close'))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($this->door->getArrayCopy())->toBe(['close']);
});

describe('the time-clock screens', function (): void {
    beforeEach(function (): void {
        $this->demo = demoCompany();
        $this->other = Tenant::factory()->create();
        $plant = WorkCenterFactory::new()->createOne(['tenant_id' => $this->demo->id, 'name' => 'Planta Querétaro']);
        $offices = WorkCenterFactory::new()->createOne(['tenant_id' => $this->demo->id, 'name' => 'Oficinas Querétaro']);
        $this->kiosk = KioskDevice::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $offices->id, 'activated_at' => now()]);
        $this->plantKiosk = KioskDevice::factory()->create(['tenant_id' => $this->demo->id, 'work_center_id' => $plant->id]);
        $this->otherKiosk = KioskDevice::factory()->create(['tenant_id' => $this->other->id, 'activated_at' => now()]);
    });

    it('issues a new link for the screen of every center, by its center\'s name, shown once with its QR, each left waiting to be opened', function (): void {
        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        $links = session('inertia.flash_data.demoKioskLinks');
        expect(array_column($links, 'center'))->toBe(['Oficinas Querétaro', 'Planta Querétaro']);

        foreach ([$this->kiosk, $this->plantKiosk] as $i => $screen) {
            $token = str($links[$i]['url'])->afterLast('/')->toString();
            expect($links[$i]['url'])->toBe(route('attendance.device.kiosk.activate', ['alba-demo', $token]))
                ->and($links[$i]['qr'])->toStartWith('data:image/svg+xml;base64,');

            $kiosk = KioskDevice::query()->withoutGlobalScopes()->findOrFail($screen->id);
            expect($kiosk->token_hash)->toBe(KioskDevice::hashToken($token))->and($kiosk->activated_at)->toBeNull();
        }
    });

    it('records each new link in the demo company\'s audit trail, without the link', function (): void {
        $admin = superadmin();
        $this->actingAs($admin)->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        $entries = DB::table('audit_entries')->where('event', 'demo.kiosk_reissued')->get();
        $tokens = array_map(fn (array $link): string => str($link['url'])->afterLast('/')->toString(), session('inertia.flash_data.demoKioskLinks'));
        expect($entries->pluck('subject_id')->sort()->values()->all())->toBe(collect([$this->kiosk->id, $this->plantKiosk->id])->sort()->values()->all())
            ->and($entries->pluck('tenant_id')->unique()->all())->toBe([$this->demo->id])
            ->and($entries->pluck('actor_id')->unique()->all())->toBe([$admin->id])
            ->and($entries->pluck('metadata')->implode(' '))->not->toContain($tokens[0])
            ->and($entries->pluck('metadata')->implode(' '))->not->toContain($tokens[1]);
    });

    it('enters the demo company only to issue them: the console goes on outside it, without the demo mark', function (): void {
        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        expect(Context::has('tenant_id'))->toBeFalse()
            ->and(Context::hasHidden(TenantContext::DEMO))->toBeFalse()
            ->and(resolve(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
    });

    it('never touches the screen of another company', function (): void {
        $before = $this->otherKiosk->token_hash;

        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'));

        expect(KioskDevice::query()->withoutGlobalScopes()->findOrFail($this->otherKiosk->id)->token_hash)->toBe($before);
    });

    it('leaves out a screen that already shows the code', function (): void {
        $this->kiosk->forceFill(['last_seen_at' => now()])->save();

        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        expect(array_column(session('inertia.flash_data.demoKioskLinks'), 'center'))->toBe(['Planta Querétaro']);
    });

    it('says so when every screen already shows the code', function (): void {
        KioskDevice::query()->withoutGlobalScopes()->where('tenant_id', $this->demo->id)->update(['last_seen_at' => now()]);

        $this->actingAs(superadmin())
            ->post(route('demo-room.kiosk.reissue'))
            ->assertSessionMissing('inertia.flash_data.demoKioskLinks')
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');
    });
});

it('answers 404 to a new screen link when there is no demo', function (): void {
    $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertNotFound();
});
