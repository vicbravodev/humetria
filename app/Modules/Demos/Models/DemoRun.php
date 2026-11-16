<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One preparation or reset of the demo company, with the step it is on. Platform data: it belongs to no tenant. At most
 * one runs at a time (a partial unique index); one left running longer than STALE_AFTER_MINUTES (a worker that died) no
 * longer holds the lock.
 *
 * @property string $id
 * @property string $kind prepare or reset
 * @property string $status running, finished or failed
 * @property string|null $step
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $error_class
 */
final class DemoRun extends Model
{
    use HasVersion4Uuids;

    /** Longer than any preparation (its job gives up at 10 minutes). */
    public const int STALE_AFTER_MINUTES = 30;

    public $timestamps = false;

    /** Starts a run; null when another one is running. */
    public static function start(string $kind): ?self
    {
        $abandoned = self::query()->where('status', 'running')->where('started_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update(['status' => 'failed', 'finished_at' => now()]);

        if ($abandoned > 0) {
            Log::warning('demo.run_abandoned');
        }

        try {
            // A savepoint: the refused insert must not spoil a transaction around it.
            $run = DB::transaction(fn (): self => self::query()->forceCreate(['kind' => $kind, 'status' => 'running', 'step' => null, 'started_at' => now()]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        Log::info('demo.run_started', ['run_id' => $run->id, 'kind' => $kind]);

        return $run;
    }

    /** The latest run, whatever its status: the console shows its progress or how long ago it finished. */
    public static function current(): ?self
    {
        return self::query()->latest('started_at')->first();
    }

    /** Records the step the run reached; the step attribute keeps the name `step`. */
    public function reach(string $label): void
    {
        $this->forceFill(['step' => $label])->save();
        Log::info('demo.run_step', ['run_id' => $this->id, 'step' => $label]);
    }

    public function finish(): void
    {
        $this->forceFill(['status' => 'finished', 'finished_at' => now()])->save();
        Log::info('demo.run_finished', ['run_id' => $this->id, 'kind' => $this->kind]);
    }

    /** Only the class is kept and logged: the message of a database error can carry values. */
    public function fail(Throwable $error): void
    {
        $this->forceFill(['status' => 'failed', 'finished_at' => now(), 'error_class' => $error::class])->save();
        Log::error('demo.run_failed', ['run_id' => $this->id, 'kind' => $this->kind, 'step' => $this->step, 'exception' => $error::class]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
