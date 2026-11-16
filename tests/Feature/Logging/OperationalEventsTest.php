<?php

declare(strict_types=1);

use App\Modules\Telework\Jobs\SendAccidentAlert;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Horizon\Lock;

/**
 * The log lines PostHog's alerts read (docs/operations/observability.md): a fixed name, ids and counts, never a payload.
 */
it('logs a job that failed for good: its class, queue and attempts, never its payload', function (): void {
    Log::spy();
    $job = new SendAccidentAlert('tenant-ana@empresa.mx', 'notice-1');

    try {
        dispatch(function () use ($job): never {
            throw new RuntimeException("failed for {$job->tenantId}");
        });
    } catch (RuntimeException) {
        // The sync queue throws it back; a worker would not.
    }

    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'queue.job_failed'
        && str_starts_with($context['job'], 'Closure (OperationalEventsTest.php:')
        && $context['queue'] === 'sync'
        && $context['connection'] === 'sync'
        && $context['attempts'] === 1
        && $context['exception'] === 'RuntimeException'
        && ! str_contains((string) json_encode($context), 'ana@empresa.mx'))->once();
});

it('warns when Horizon sees a queue waiting too long', function (): void {
    // Horizon's own listener (a notification nobody is routed to) takes a lock in Redis first: declined here.
    app()->instance(Lock::class, Mockery::mock(Lock::class, ['get' => false]));
    Log::spy();

    event(new LongWaitDetected('redis', 'default', 75));

    Log::shouldHaveReceived('warning')->with('queue.long_wait', ['connection' => 'redis', 'queue' => 'default', 'seconds' => 75])->once();
});

it('logs any scheduled command that fails, by its name and the class of the error', function (): void {
    Log::spy();
    $task = collect(app(Schedule::class)->events())->sole(fn (ScheduledEvent $event): bool => str_ends_with($event->command ?? '', 'campaigns:close-expired'));

    event(new ScheduledTaskFailed($task, new RuntimeException('ana@empresa.mx'), 1.5));

    Log::shouldHaveReceived('error')->with('schedule.command_failed', [
        'command' => 'campaigns:close-expired',
        'exception' => 'RuntimeException',
        'runtime' => 1.5,
    ])->once();
});

it('beats every five minutes, so PostHog can tell when the scheduler stopped', function (): void {
    $heartbeat = collect(app(Schedule::class)->events())
        ->sole(fn (ScheduledEvent $event): bool => Str::after($event->command ?? '', "'artisan' ") === 'schedule:heartbeat');
    Log::spy();

    $this->artisan('schedule:heartbeat')->assertSuccessful();

    expect($heartbeat->expression)->toBe('*/5 * * * *');
    Log::shouldHaveReceived('info')->with('schedule.heartbeat')->once();
});
