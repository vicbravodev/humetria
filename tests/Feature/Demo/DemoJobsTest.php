<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Demos\Jobs\PrepareDemo;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Event;

/*
 * The console prepares or resets the demo through a job that carries only its run: the run shows each step, and how it
 * ended. A failure keeps the step it reached and the class of the error, never its message.
 */

/** A workspace that tells two steps, or fails on the second; it notes what it was asked. */
function demoWorkspaceSpy(bool $fails = false): ArrayObject
{
    $calls = new ArrayObject;
    app()->instance(DemoWorkspace::class, new readonly class($calls, $fails) implements DemoWorkspace
    {
        /** @param ArrayObject<int, string> $calls */
        public function __construct(private ArrayObject $calls, private bool $fails) {}

        public function prepare(?Closure $step = null): void
        {
            $this->calls->append('prepare');
            $step?->__invoke('Organización');
            $step?->__invoke('NOM-035');

            if ($this->fails) {
                throw new RuntimeException('SQLSTATE: la fila de juan@empresa.mx');
            }
        }

        public function reset(): void
        {
            $this->calls->append('reset');
        }
    });

    return $calls;
}

function startedRun(string $kind): DemoRun
{
    return DemoRun::start($kind) ?? throw new LogicException('Another run is in progress.');
}

it('runs once, for up to ten minutes, on the queue', function (string $job): void {
    $instance = new $job('run');

    expect($instance)->toBeInstanceOf(ShouldQueue::class)
        ->and($instance->tries)->toBe(1)
        ->and($instance->timeout)->toBe(600)
        ->and($instance->failOnTimeout)->toBeTrue();
})->with([PrepareDemo::class, ResetDemo::class]);

it('runs on a queue whose workers wait for it longer than it may take', function (string $job, string $environment): void {
    $instance = new $job('run');
    // Horizon runs, in each environment, the supervisors it names there, each over its defaults.
    $supervisors = [];
    foreach (config()->array("horizon.environments.{$environment}") as $name => $settings) {
        $supervisor = [...config()->array('horizon.defaults')[$name] ?? [], ...$settings];

        if ($supervisor['connection'] === $instance->connection && in_array($instance->queue, $supervisor['queue'], true)) {
            $supervisors[] = $supervisor;
        }
    }

    // Redis hands a job to another worker once retry_after passes: a preparation still running would be told twice.
    expect(config()->integer("queue.connections.{$instance->connection}.retry_after"))->toBeGreaterThan($instance->timeout)
        ->and($supervisors)->toHaveCount(1)
        ->and($supervisors[0]['timeout'])->toBeGreaterThanOrEqual($instance->timeout)
        ->and($supervisors[0]['timeout'])->toBeLessThan(config()->integer("queue.connections.{$instance->connection}.retry_after"));
})->with([PrepareDemo::class, ResetDemo::class])->with(['production', 'local']);

it('prepares the demo step by step and finishes its run', function (): void {
    $calls = demoWorkspaceSpy();
    $run = startedRun('prepare');

    PrepareDemo::dispatchSync($run->id);

    expect($calls->getArrayCopy())->toBe(['prepare'])
        ->and($run->fresh()?->status)->toBe('finished')
        ->and($run->fresh()?->step)->toBe('NOM-035');
});

it('resets the demo by preparing it again, which wipes it once first', function (): void {
    $calls = demoWorkspaceSpy();
    $run = startedRun('reset');

    ResetDemo::dispatchSync($run->id);

    expect($calls->getArrayCopy())->toBe(['prepare'])
        ->and($run->fresh()?->status)->toBe('finished');
});

it('records the failure on its run without the message, and frees the lock', function (string $job): void {
    demoWorkspaceSpy(fails: true);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });
    $run = startedRun('prepare');

    $job::dispatchSync($run->id);

    expect($run->fresh()?->status)->toBe('failed')
        ->and($run->fresh()?->error_class)->toBe(RuntimeException::class)
        ->and($run->fresh()?->step)->toBe('NOM-035')
        ->and(implode("\n", $logged))->toContain('demo.run_failed')->not->toContain('juan@empresa.mx')
        ->and(DemoRun::start('reset'))->toBeInstanceOf(DemoRun::class);
})->with([PrepareDemo::class, ResetDemo::class]);

it('does nothing when its run was taken over before a worker picked it up', function (string $job): void {
    $calls = demoWorkspaceSpy();
    $this->freezeSecond();
    $abandoned = startedRun('prepare');
    $this->travel(31)->minutes();
    $current = startedRun('reset');

    $job::dispatchSync($abandoned->id);

    expect($calls->getArrayCopy())->toBe([])
        ->and($abandoned->fresh()?->status)->toBe('failed')
        ->and($abandoned->fresh()?->step)->toBeNull()
        ->and($current->fresh()?->status)->toBe('running');
})->with([PrepareDemo::class, ResetDemo::class]);

it('records a job the worker gave up on as failed', function (): void {
    $run = startedRun('prepare');

    (new PrepareDemo($run->id))->failed(new TimeoutExceededException('PrepareDemo has timed out.'));

    expect($run->fresh()?->status)->toBe('failed')
        ->and($run->fresh()?->error_class)->toBe(TimeoutExceededException::class);
});
