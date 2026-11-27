<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Jobs\PrepareDemoSession;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/*
 * A demo session: the superadmin starts one for a client, with the tours to show; the demo resets and gets ready for the
 * real audience, and ending it resets the demo again, so whatever the audience left goes with it. One at a time.
 */

/** A workspace that tells one step, or fails (preparing or staging the session); it notes what it was asked. */
function sessionWorkspace(bool $fails = false, bool $stagingFails = false): ArrayObject
{
    $calls = new ArrayObject;
    app()->instance(DemoWorkspace::class, new readonly class($calls, $fails, $stagingFails) implements DemoWorkspace
    {
        /** @param ArrayObject<int, string> $calls */
        public function __construct(private ArrayObject $calls, private bool $fails, private bool $stagingFails) {}

        public function prepare(?Closure $step = null): void
        {
            $this->calls->append('prepare');
            $step?->__invoke('Organización');

            if ($this->fails) {
                throw new RuntimeException('SQLSTATE: la fila de juan@empresa.mx');
            }
        }

        public function reset(): void
        {
            $this->calls->append('reset');
        }

        public function stageSession(?string $clientName): string
        {
            $this->calls->append('stage '.($clientName ?? '-'));

            if ($this->stagingFails) {
                throw new RuntimeException('SQLSTATE: el centro de juan@empresa.mx');
            }

            return '0b7c2f3e-5d1a-4c8e-9f60-3a2b1c4d5e6f';
        }
    });

    return $calls;
}

/** @param array<string, mixed> $overrides */
function sessionForm(array $overrides = []): array
{
    return ['client_name' => 'Grupo Ejemplo', 'tours' => ['attendance', 'nom035'], ...$overrides];
}

function openSession(DemoSessionStatus $status = DemoSessionStatus::Active, ?string $client = null): DemoSession
{
    return DemoSession::query()->forceCreate(['client_name' => $client, 'tours' => ['nom035'], 'status' => $status, 'started_at' => now()]);
}

it('lives at /admin/demo/session', function (): void {
    expect(route('demo-room.session.start', absolute: false))->toBe('/admin/demo/session')
        ->and(route('demo-room.session.end', absolute: false))->toBe('/admin/demo/session');
});

it('starts a session that resets the demo', function (): void {
    Bus::fake();
    $demo = Tenant::factory()->demo()->create();
    $admin = superadmin();

    $this->actingAs($admin)
        ->post(route('demo-room.session.start'), sessionForm())
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Preparando la sesión: tarda alrededor de medio minuto.']);

    $session = DemoSession::query()->sole();
    $run = DemoRun::query()->sole();
    expect($session->status)->toBe(DemoSessionStatus::Preparing)
        ->and($session->client_name)->toBe('Grupo Ejemplo')
        ->and($session->tours)->toBe(['attendance', 'nom035'])
        ->and($session->skipped_steps)->toBe([])
        ->and($session->run_id)->toBe($run->id)
        ->and($run->kind)->toBe('session')
        ->and($run->status)->toBe('running');
    Bus::assertDispatched(PrepareDemoSession::class, fn (PrepareDemoSession $job): bool => $job->runId === $run->id && $job->sessionId === $session->id);

    $entry = DB::table('audit_entries')->where('event', 'demo.session_started')->sole();
    expect($entry->tenant_id)->toBe($demo->id)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe($session->id)
        ->and($entry->metadata)->not->toContain('Grupo Ejemplo');
});

it('starts a session without a client name', function (): void {
    Bus::fake();

    $this->actingAs(superadmin())->post(route('demo-room.session.start'), sessionForm(['client_name' => '']))->assertRedirect()->assertSessionHasNoErrors();

    expect(DemoSession::query()->sole()->client_name)->toBeNull();
});

it('refuses a second session while one is open', function (DemoSessionStatus $status): void {
    Bus::fake();
    openSession($status);

    $this->actingAs(superadmin())
        ->post(route('demo-room.session.start'), sessionForm())
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Ya hay una sesión en curso o una preparación corriendo: espera a que termine.']);

    expect(DemoSession::query()->count())->toBe(1)->and(DemoRun::query()->count())->toBe(0);
    Bus::assertNotDispatched(PrepareDemoSession::class);
})->with([DemoSessionStatus::Preparing, DemoSessionStatus::Active, DemoSessionStatus::Ending]);

it('starts a new session once the last one ended or failed', function (DemoSessionStatus $status): void {
    Bus::fake();
    openSession($status);

    $this->actingAs(superadmin())->post(route('demo-room.session.start'), sessionForm())->assertRedirect();

    expect(DemoSession::current()?->status)->toBe(DemoSessionStatus::Preparing);
})->with([DemoSessionStatus::Ended, DemoSessionStatus::Failed]);

it('leaves no session behind when a preparation of the demo is already running', function (): void {
    Bus::fake();
    DemoRun::start('reset');

    $this->actingAs(superadmin())
        ->post(route('demo-room.session.start'), sessionForm())
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(DemoSession::query()->count())->toBe(0)->and(DB::table('audit_entries')->where('event', 'demo.session_started')->count())->toBe(0);
});

it('fails the session when the queue cannot take its preparation', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused [redis-long]'));

    $this->actingAs(superadmin())
        ->post(route('demo-room.session.start'), sessionForm())
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No se pudo encolar la preparación: inténtalo de nuevo en unos minutos.']);

    expect(DemoSession::query()->sole()->status)->toBe(DemoSessionStatus::Failed)
        ->and(DemoSession::current())->toBeNull()
        ->and(DemoRun::query()->sole()->status)->toBe('failed');
});

it('answers 404 to anyone but the superadmin, before anything runs', function (Closure $user, string $method, string $route): void {
    Bus::fake();
    $session = openSession();

    $this->actingAs($user())->{$method}(route($route), ['tours' => []])->assertNotFound()->assertSessionHasNoErrors();

    expect(DemoSession::query()->sole()->status)->toBe($session->status)->and(DemoRun::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
})->with([
    // A Closure parameter: Pest hands each one over as is, to be called inside the test.
    'the demo\'s HR' => fn (): User => memberOf(Tenant::factory()->demo()->create(), TenantRole::Hr),
    'a real company\'s owner' => fn (): User => ownerOf(Tenant::factory()->create()),
    'support' => fn (): User => support(),
])->with([
    'start' => ['post', 'demo-room.session.start'],
    'end' => ['delete', 'demo-room.session.end'],
]);

it('sends guests to the login', function (string $method, string $route): void {
    $this->{$method}(route($route))->assertRedirect(route('login'));
})->with([
    'start' => ['post', 'demo-room.session.start'],
    'end' => ['delete', 'demo-room.session.end'],
]);

it('validates the tours and the client name', function (array $body, string $field): void {
    Bus::fake();

    $this->actingAs(superadmin())->post(route('demo-room.session.start'), sessionForm($body))->assertSessionHasErrors($field);

    expect(DemoSession::query()->count())->toBe(0);
})->with([
    'no tours' => [['tours' => []], 'tours'],
    'tours missing' => [['tours' => null], 'tours'],
    'a repeated tour' => [['tours' => ['nom035', 'nom035']], 'tours.1'],
    'an unknown tour' => [['tours' => ['payroll']], 'tours.0'],
    'the base tour, which always goes first' => [['tours' => ['base']], 'tours.0'],
    'more than six' => [['tours' => ['nom035', 'attendance', 'engagement', 'complaints', 'leave', 'humi', 'nom035']], 'tours'],
    'not a list' => [['tours' => ['a' => 'nom035']], 'tours'],
    'a client name of 81' => [['client_name' => str_repeat('a', 81)], 'client_name'],
    'a client name of two lines' => [['client_name' => "Grupo\nEjemplo"], 'client_name'],
]);

it('accepts the six tours and a client name of 80', function (): void {
    Bus::fake();
    $tours = ['humi', 'leave', 'complaints', 'engagement', 'attendance', 'nom035'];

    $this->actingAs(superadmin())->post(route('demo-room.session.start'), sessionForm(['tours' => $tours, 'client_name' => str_repeat('a', 80)]))->assertSessionHasNoErrors();

    expect(DemoSession::query()->sole()->tours)->toBe($tours);
});

it('ends the session with a reset and marks it ended when the reset finishes', function (): void {
    Queue::fake();
    $demo = Tenant::factory()->demo()->create();
    $session = openSession();
    $calls = sessionWorkspace();

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Terminando la sesión: la demo se reinicia y borra lo que dejó el público.']);

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
    $run = DemoRun::query()->sole();
    expect($run->kind)->toBe('reset');
    Queue::assertPushed(ResetDemo::class, fn (ResetDemo $job): bool => $job->runId === $run->id);
    $entry = DB::table('audit_entries')->where('event', 'demo.session_ended')->sole();
    expect($entry->subject_id)->toBe($session->id)->and($entry->tenant_id)->toBe($demo->id);

    resolve(ResetDemo::class, ['runId' => $run->id])->handle(resolve(DemoWorkspace::class));

    expect($calls->getArrayCopy())->toBe(['prepare'])
        ->and($session->refresh()->status)->toBe(DemoSessionStatus::Ended)
        ->and($session->ended_at)->not->toBeNull()
        ->and(DemoSession::current())->toBeNull();
});

it('keeps the session ending when its reset fails, and ends it on a second try', function (): void {
    Queue::fake();
    $session = openSession();
    sessionWorkspace(fails: true);
    $this->actingAs(superadmin());

    $this->delete(route('demo-room.session.end'));
    resolve(ResetDemo::class, ['runId' => DemoRun::query()->sole()->id])->handle(resolve(DemoWorkspace::class));
    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);

    sessionWorkspace();
    $this->delete(route('demo-room.session.end'))->assertSessionHas('inertia.flash_data.toast.type', 'success');
    resolve(ResetDemo::class, ['runId' => DemoRun::query()->where('status', 'running')->sole()->id])->handle(resolve(DemoWorkspace::class));

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ended);
});

it('leaves a plain reset alone when no session is ending', function (): void {
    $session = openSession();
    sessionWorkspace();
    $run = DemoRun::start('reset') ?? throw new LogicException('Not started.');

    resolve(ResetDemo::class, ['runId' => $run->id])->handle(resolve(DemoWorkspace::class));

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Active);
});

it('says so when there is no session to end', function (): void {
    Queue::fake();

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No hay una sesión en curso.']);

    expect(DemoRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('ends a session whose preparation failed: the demo resets clean and the room forgets it', function (): void {
    Queue::fake();
    $session = openSession(DemoSessionStatus::Failed);
    sessionWorkspace();

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
    resolve(ResetDemo::class, ['runId' => DemoRun::query()->sole()->id])->handle(resolve(DemoWorkspace::class));

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ended);
});

it('ends only the last session when it failed, never an older failed one', function (): void {
    Queue::fake();
    $failed = openSession(DemoSessionStatus::Failed);
    $failed->forceFill(['started_at' => now()->subHour()])->save();
    openSession(DemoSessionStatus::Ended);

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No hay una sesión en curso.']);

    expect($failed->refresh()->status)->toBe(DemoSessionStatus::Failed);
    Queue::assertNothingPushed();
});

it('leaves a failed session failed when a new session opened before its end', function (): void {
    $failed = openSession(DemoSessionStatus::Failed);

    // Another request opens a session between this one reading the failed session and moving it to ending.
    DemoSession::query()->forceCreate(['tours' => ['nom035'], 'status' => DemoSessionStatus::Preparing, 'started_at' => now()->subHour()]);

    expect($failed->markEnding())->toBeFalse()
        ->and($failed->refresh()->status)->toBe(DemoSessionStatus::Failed);
});

it('keeps the session as it was when the demo is busy and cannot reset', function (): void {
    Queue::fake();
    $session = openSession(DemoSessionStatus::Preparing);
    DemoRun::start('session');

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Hay una preparación corriendo: espera a que termine para terminar la sesión.']);

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Preparing)
        ->and(DB::table('audit_entries')->where('event', 'demo.session_ended')->count())->toBe(0);
});

it('leaves the end to the request that moved the session first', function (): void {
    Queue::fake();
    $session = openSession();
    // Another click moves it to ending right after this one read it.
    DemoSession::retrieved(fn () => DB::table('demo_sessions')->where('status', 'active')->update(['status' => 'ending']));

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending)
        ->and(DemoRun::query()->count())->toBe(0)
        ->and(DB::table('audit_entries')->where('event', 'demo.session_ended')->count())->toBe(0);
});

it('keeps an ending session ending when its second try cannot reset', function (): void {
    $session = openSession(DemoSessionStatus::Ending);
    DemoRun::start('reset');
    Log::spy();

    $this->actingAs(superadmin())->delete(route('demo-room.session.end'));

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
    Log::shouldNotHaveReceived('info', ['demo.session_ending_undone', Mockery::any()]);
});

it('keeps the session as it was when the queue cannot take the reset', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused [redis-long]'));
    $session = openSession();

    $this->actingAs(superadmin())
        ->delete(route('demo-room.session.end'))
        ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'No se pudo encolar la preparación: inténtalo de nuevo en unos minutos.']);

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Active);
});

it('moves the session to ending once, logging it with its id', function (): void {
    $session = openSession();
    $stale = DemoSession::query()->findOrFail($session->id);
    Log::spy();

    expect($session->markEnding())->toBeTrue()
        ->and($session->status)->toBe(DemoSessionStatus::Ending)
        ->and($stale->markEnding())->toBeFalse()
        ->and($session->markEnding())->toBeFalse()
        ->and($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
    Log::shouldHaveReceived('info')->with('demo.session_ending', ['session_id' => $session->id])->once();
});

it('gives the session its status back only while it is still ending', function (): void {
    $session = openSession();
    $session->markEnding();
    $late = DemoSession::query()->findOrFail($session->id);
    $late->markEnded();

    $session->undoEnding(DemoSessionStatus::Active);

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ended);

    $other = openSession();
    $other->markEnding();
    $other->undoEnding(DemoSessionStatus::Active);

    expect($other->refresh()->status)->toBe(DemoSessionStatus::Active);
});

describe('the preparation of a session', function (): void {
    it('runs once, for up to ten minutes, on the demo queue', function (): void {
        $job = new PrepareDemoSession('run', 'session');

        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($job->tries)->toBe(1)
            ->and($job->timeout)->toBe(600)
            ->and($job->failOnTimeout)->toBeTrue()
            ->and($job->connection)->toBe((new ResetDemo('run'))->connection)
            ->and($job->queue)->toBe('demo');
    });

    it('prepares the demo, stages its room and opens the session in it', function (): void {
        $calls = sessionWorkspace();
        $session = openSession(DemoSessionStatus::Preparing, 'Grupo Ejemplo');
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($calls->getArrayCopy())->toBe(['prepare', 'stage Grupo Ejemplo'])
            ->and($run->refresh()->status)->toBe('finished')
            ->and($run->step)->toBe('Sala de la sesión')
            ->and($session->refresh()->status)->toBe(DemoSessionStatus::Active)
            ->and($session->work_center_id)->toBe('0b7c2f3e-5d1a-4c8e-9f60-3a2b1c4d5e6f')
            ->and($session->ready_at)->not->toBeNull();
    });

    it('marks the session failed when staging fails', function (): void {
        sessionWorkspace(stagingFails: true);
        $session = openSession(DemoSessionStatus::Preparing);
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($run->refresh()->status)->toBe('failed')
            ->and($run->step)->toBe('Sala de la sesión')
            ->and($run->error_class)->toBe(RuntimeException::class)
            ->and($session->refresh()->status)->toBe(DemoSessionStatus::Failed)
            ->and($session->work_center_id)->toBeNull()
            ->and(DemoSession::current())->toBeNull();
    });

    it('fails the session with its run, keeping only the class', function (): void {
        sessionWorkspace(fails: true);
        $session = openSession(DemoSessionStatus::Preparing);
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($run->refresh()->status)->toBe('failed')
            ->and($run->error_class)->toBe(RuntimeException::class)
            ->and($session->refresh()->status)->toBe(DemoSessionStatus::Failed)
            ->and($session->ended_at)->not->toBeNull()
            ->and(DemoSession::current())->toBeNull();
    });

    it('fails the session when the worker gives up on it', function (): void {
        $session = openSession(DemoSessionStatus::Preparing);
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->failed(new TimeoutExceededException('Timed out.'));

        expect($run->refresh()->status)->toBe('failed')->and($session->refresh()->status)->toBe(DemoSessionStatus::Failed);
    });

    it('does not reopen a session that started ending while it prepared', function (DemoSessionStatus $status): void {
        sessionWorkspace();
        $session = openSession($status);
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($session->refresh()->status)->toBe($status)->and($session->ready_at)->toBeNull();
    })->with([DemoSessionStatus::Ending, DemoSessionStatus::Ended]);

    it('does not fail a session that is no longer preparing', function (DemoSessionStatus $status): void {
        sessionWorkspace(fails: true);
        $session = openSession($status);
        $run = DemoRun::start('session') ?? throw new LogicException('Not started.');

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));
        (new PrepareDemoSession($run->id, $session->id))->failed(new TimeoutExceededException('Timed out.'));

        expect($session->refresh()->status)->toBe($status)->and($session->ended_at)->toBeNull();
    })->with([DemoSessionStatus::Active, DemoSessionStatus::Ending]);

    it('prepares nothing and fails its session when its run was taken over while it waited', function (): void {
        $calls = sessionWorkspace();
        $session = openSession(DemoSessionStatus::Preparing);
        $run = DemoRun::query()->forceCreate(['kind' => 'session', 'status' => 'failed', 'started_at' => now(), 'finished_at' => now()]);

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($calls->getArrayCopy())->toBe([])
            ->and($session->refresh()->status)->toBe(DemoSessionStatus::Failed)
            ->and(DemoSession::current())->toBeNull();
    });

    it('leaves an ending session ending when its run was taken over', function (): void {
        sessionWorkspace();
        $session = openSession(DemoSessionStatus::Ending);
        $run = DemoRun::query()->forceCreate(['kind' => 'session', 'status' => 'failed', 'started_at' => now(), 'finished_at' => now()]);

        (new PrepareDemoSession($run->id, $session->id))->handle(resolve(DemoWorkspace::class));

        expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending);
    });
});

it('lets the database refuse a status it does not know and a second open session', function (): void {
    openSession();

    expect(fn () => DB::transaction(fn () => openSession(DemoSessionStatus::Preparing)))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('demo_sessions')->insert(['id' => Illuminate\Support\Str::uuid()->toString(), 'tours' => '[]', 'status' => 'paused', 'started_at' => now()])))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('records each panel page the presenter visits, once', function (): void {
    $session = openSession();

    $session->recordVisit('tenant.campaigns.results');
    $session->recordVisit('tenant.attendance.report');
    $session->recordVisit('tenant.campaigns.results');

    expect($session->visited)->toBe(['tenant.campaigns.results', 'tenant.attendance.report'])
        ->and($session->refresh()->visited)->toBe(['tenant.campaigns.results', 'tenant.attendance.report'])
        ->and(DemoSession::query()->sole()->visited)->toBe(['tenant.campaigns.results', 'tenant.attendance.report']);
});

it('does not repeat a visit another request already recorded', function (): void {
    $session = openSession();
    DemoSession::query()->sole()->recordVisit('tenant.campaigns.results');

    $session->recordVisit('tenant.campaigns.results');

    expect($session->refresh()->visited)->toBe(['tenant.campaigns.results']);
});
