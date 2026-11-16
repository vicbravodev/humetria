<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRun;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/*
 * Preparing or resetting the demo runs in a job, one at a time: the lock is the database's (one running row at most), so
 * two consoles pressing at once start one run. A worker that died leaves its run behind; after a while it no longer counts.
 */
it('refuses a second run while one is in progress', function (): void {
    $run = DemoRun::start('prepare');

    expect($run)->toBeInstanceOf(DemoRun::class)
        ->and($run?->status)->toBe('running')
        ->and(DemoRun::start('reset'))->toBeNull()
        ->and(DemoRun::query()->count())->toBe(1);
});

it('lets the database itself refuse a second running run', function (): void {
    DemoRun::start('prepare');

    expect(fn () => DB::table('demo_runs')->insert(['id' => Str::uuid()->toString(), 'kind' => 'reset', 'status' => 'running', 'started_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('starts again once the run finished or failed', function (): void {
    DemoRun::start('prepare')?->finish();
    DemoRun::start('reset')?->fail(new RuntimeException('se cayó'));

    expect(DemoRun::start('reset'))->toBeInstanceOf(DemoRun::class);
});

it('takes over a run left running for more than 30 minutes, not before', function (): void {
    $this->freezeSecond();
    $abandoned = DemoRun::start('prepare');

    $this->travel(30)->minutes();
    expect(DemoRun::start('reset'))->toBeNull();

    $this->travel(1)->seconds();
    $run = DemoRun::start('reset');

    expect($run?->kind)->toBe('reset')
        ->and($abandoned?->fresh()?->status)->toBe('failed')
        ->and($abandoned?->fresh()?->finished_at?->equalTo(now()))->toBeTrue();
});

it('records each step and when it finished', function (): void {
    $this->freezeSecond();
    $run = DemoRun::start('prepare');

    $run?->reach('Organización');
    $run?->reach('NOM-035');
    $this->travel(2)->minutes();
    $run?->finish();

    $stored = DemoRun::query()->sole();
    expect($stored->step)->toBe('NOM-035')
        ->and($stored->status)->toBe('finished')
        ->and($stored->finished_at?->equalTo(now()))->toBeTrue()
        ->and($stored->started_at->equalTo(now()->subMinutes(2)))->toBeTrue()
        ->and($stored->error_class)->toBeNull();
});

it('records progress and failure without the exception message', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = [$log->level, $log->message, $log->context];
    });
    $run = DemoRun::start('reset');
    $run?->reach('Asistencia');

    $run?->fail(new RuntimeException('SQLSTATE: la fila de juan@empresa.mx'));

    $stored = DemoRun::query()->sole();
    $failure = array_values(array_filter($logged, fn (array $log): bool => $log[1] === 'demo.run_failed'));
    expect($stored->status)->toBe('failed')
        ->and($stored->error_class)->toBe(RuntimeException::class)
        ->and($stored->step)->toBe('Asistencia')
        ->and($failure)->toHaveCount(1)
        ->and($failure[0][0])->toBe('error')
        ->and($failure[0][2])->toBe(['run_id' => $stored->id, 'kind' => 'reset', 'step' => 'Asistencia', 'exception' => RuntimeException::class])
        ->and(json_encode([$logged, DemoRun::query()->sole()->toArray()]))->not->toContain('juan@empresa.mx');
});

it('says which run is the latest', function (): void {
    $this->freezeSecond();
    expect(DemoRun::current())->toBeNull();

    DemoRun::start('prepare')?->finish();
    $this->travel(1)->minutes();
    $latest = DemoRun::start('reset');

    expect(DemoRun::current()?->id)->toBe($latest?->id);
});

it('names its runs with random ids', function (): void {
    expect(DemoRun::start('prepare')?->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});
