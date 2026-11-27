<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/*
 * The backstop of «Terminar sesión»: every hour a command ends the session that has been open for more than 24 hours
 * (the reset wipes what the audience left), and frees the lock of one stuck preparing or ending because its run died.
 */

beforeEach(function (): void {
    // The marks are exact: 24 h and STALE_AFTER_MINUTES still count as young.
    $this->freezeSecond();
    Queue::fake();
    Tenant::factory()->demo()->create();
});

function sessionOpenedAgo(DateInterval|string $ago, DemoSessionStatus $status = DemoSessionStatus::Active, ?string $runId = null): DemoSession
{
    return DemoSession::query()->forceCreate([
        'client_name' => 'Grupo Ejemplo',
        'tours' => ['nom035'],
        'status' => $status,
        'run_id' => $runId,
        'started_at' => now()->sub($ago),
    ]);
}

function runStartedAgo(string $status, int $minutes, string $kind = 'session'): DemoRun
{
    return DemoRun::query()->forceCreate([
        'kind' => $kind,
        'status' => $status,
        'started_at' => now()->subMinutes($minutes),
        'finished_at' => $status === 'running' ? null : now(),
    ]);
}

it('ends a session older than 24 hours', function (DemoSessionStatus $status): void {
    $session = sessionOpenedAgo(new DateInterval('PT24H1S'), $status);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sesión vencida: terminando.')->assertSuccessful();

    $run = DemoRun::query()->sole();
    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending)
        ->and($run->kind)->toBe('reset')
        ->and(DB::table('audit_entries')->where('event', 'demo.session_ended')->where('subject_id', $session->id)->count())->toBe(1);
    Queue::assertPushed(ResetDemo::class, fn (ResetDemo $job): bool => $job->runId === $run->id);
})->with([DemoSessionStatus::Active, DemoSessionStatus::Preparing]);

it('leaves a younger one', function (): void {
    $session = sessionOpenedAgo(new DateInterval('PT24H'));

    $this->artisan('demo:end-stale-session')->expectsOutput('Sin cambios.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Active)->and(DemoRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('logs demo.session_expired', function (): void {
    $session = sessionOpenedAgo(new DateInterval('P2D'));
    Log::spy();

    $this->artisan('demo:end-stale-session')->assertSuccessful();

    Log::shouldHaveReceived('info')->with('demo.session_expired', ['session_id' => $session->id])->once();
});

it('tries again later when the reset of an expired session cannot start', function (): void {
    $session = sessionOpenedAgo(new DateInterval('P2D'));
    runStartedAgo('running', 5, 'prepare');

    $this->artisan('demo:end-stale-session')->expectsOutput('La demo está ocupada: se intenta de nuevo en una hora.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Active);
});

it('fails a session stuck preparing because its run died', function (string $status, int $minutes): void {
    $run = runStartedAgo($status, $minutes);
    $session = sessionOpenedAgo(new DateInterval('PT1H'), DemoSessionStatus::Preparing, $run->id);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sesión atorada preparándose: marcada fallida.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Failed)->and(DemoSession::current())->toBeNull();
    Queue::assertNothingPushed();
})->with([
    'failed' => ['failed', 5],
    'running past the stale mark' => ['running', DemoRun::STALE_AFTER_MINUTES + 1],
]);

it('fails a session preparing with no run past the stale mark', function (): void {
    $session = sessionOpenedAgo(new DateInterval('PT'.(DemoRun::STALE_AFTER_MINUTES + 1).'M'), DemoSessionStatus::Preparing);

    $this->artisan('demo:end-stale-session')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Failed);
});

it('leaves a session preparing with a live run', function (?int $runMinutes): void {
    $run = $runMinutes === null ? null : runStartedAgo('running', $runMinutes);
    $session = sessionOpenedAgo(new DateInterval('PT10M'), DemoSessionStatus::Preparing, $run?->id);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sin cambios.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Preparing);
})->with([
    'running' => [DemoRun::STALE_AFTER_MINUTES],
    'its run about to be created' => [null],
]);

it('ends again a session stuck ending because its reset died', function (string $status, int $minutes): void {
    runStartedAgo($status, $minutes, 'reset');
    $session = sessionOpenedAgo(new DateInterval('PT2H'), DemoSessionStatus::Ending);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sesión atorada terminándose: reinicio de nuevo.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending)
        ->and(DemoRun::query()->where('status', 'running')->sole()->kind)->toBe('reset')
        ->and(DB::table('audit_entries')->where('event', 'demo.session_ended')->count())->toBe(0);
    Queue::assertPushed(ResetDemo::class);
})->with([
    'failed' => ['failed', 5],
    'running past the stale mark' => ['running', DemoRun::STALE_AFTER_MINUTES + 1],
]);

it('leaves a session ending while its reset runs or just finished', function (string $status): void {
    runStartedAgo($status, 5, 'reset');
    $session = sessionOpenedAgo(new DateInterval('PT2H'), DemoSessionStatus::Ending);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sin cambios.')->assertSuccessful();

    expect($session->refresh()->status)->toBe(DemoSessionStatus::Ending)->and(DemoRun::query()->count())->toBe(1);
    Queue::assertNothingPushed();
})->with(['running', 'finished']);

it('does nothing without an open session', function (): void {
    sessionOpenedAgo(new DateInterval('P3D'), DemoSessionStatus::Ended);

    $this->artisan('demo:end-stale-session')->expectsOutput('Sin cambios.')->assertSuccessful();

    expect(DemoRun::query()->count())->toBe(0);
});

it('runs every hour, once and without overlapping', function (): void {
    $event = collect(app(Schedule::class)->events())->sole(fn (Event $event): bool => str_contains($event->command ?? '', 'demo:end-stale-session'));

    expect($event->expression)->toBe('0 * * * *')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
});
