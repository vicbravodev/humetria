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

it('prepares the demo step by step and finishes its run', function (): void {
    $calls = demoWorkspaceSpy();
    $run = startedRun('prepare');

    PrepareDemo::dispatch($run->id);

    expect($calls->getArrayCopy())->toBe(['prepare'])
        ->and($run->fresh()?->status)->toBe('finished')
        ->and($run->fresh()?->step)->toBe('NOM-035');
});

it('resets the demo, then prepares it again', function (): void {
    $calls = demoWorkspaceSpy();
    $run = startedRun('reset');

    ResetDemo::dispatch($run->id);

    expect($calls->getArrayCopy())->toBe(['reset', 'prepare'])
        ->and($run->fresh()?->status)->toBe('finished');
});

it('records the failure on its run without the message, and frees the lock', function (string $job): void {
    demoWorkspaceSpy(fails: true);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });
    $run = startedRun('prepare');

    $job::dispatch($run->id);

    expect($run->fresh()?->status)->toBe('failed')
        ->and($run->fresh()?->error_class)->toBe(RuntimeException::class)
        ->and($run->fresh()?->step)->toBe('NOM-035')
        ->and(implode("\n", $logged))->toContain('demo.run_failed')->not->toContain('juan@empresa.mx')
        ->and(DemoRun::start('reset'))->toBeInstanceOf(DemoRun::class);
})->with([PrepareDemo::class, ResetDemo::class]);

it('records a job the worker gave up on as failed', function (): void {
    $run = startedRun('prepare');

    (new PrepareDemo($run->id))->failed(new TimeoutExceededException('PrepareDemo has timed out.'));

    expect($run->fresh()?->status)->toBe('failed')
        ->and($run->fresh()?->error_class)->toBe(TimeoutExceededException::class);
});
