<?php

declare(strict_types=1);

namespace App\Modules\Demos\Models;

use App\Modules\Demos\Enums\DemoSessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A demo session for a client: the demo resets for it, opens to the real audience, and resets again when it ends, taking
 * whatever the audience left. Platform data: it belongs to no tenant. At most one is open at a time (a partial unique
 * index over preparing, active and ending). The client's name is never logged nor audited.
 *
 * @property string $id
 * @property string|null $client_name
 * @property list<string> $tours
 * @property string|null $work_center_id
 * @property list<string> $skipped_steps
 * @property list<string> $visited
 * @property DemoSessionStatus $status
 * @property string|null $run_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $ended_at
 */
final class DemoSession extends Model
{
    use HasVersion4Uuids;

    private const array OPEN = [DemoSessionStatus::Preparing, DemoSessionStatus::Active, DemoSessionStatus::Ending];

    public $timestamps = false;

    /** @var array<string, string> */
    protected $attributes = ['visited' => '[]'];

    /**
     * Opens a session, preparing; null when another one is open.
     *
     * @param  list<string>  $tours
     */
    public static function start(?string $clientName, array $tours): ?self
    {
        try {
            // A savepoint: the refused insert must not spoil a transaction around it.
            $session = DB::transaction(fn (): self => self::query()->forceCreate([
                'client_name' => $clientName,
                'tours' => $tours,
                'skipped_steps' => [],
                'status' => DemoSessionStatus::Preparing,
                'started_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        return $session;
    }

    /** The open session (preparing, active or ending), if any. */
    public static function current(): ?self
    {
        return self::query()->whereIn('status', self::OPEN)->first();
    }

    /** The session under way: open, and ready for the audience. */
    public static function active(): ?self
    {
        return self::query()->where('status', DemoSessionStatus::Active)->first();
    }

    /** Its demo is ready for the audience. Task 2 passes the session's center; until then there is none. */
    public function markReady(?string $workCenterId): void
    {
        $this->forceFill(['status' => DemoSessionStatus::Active, 'work_center_id' => $workCenterId, 'ready_at' => now()])->save();
        Log::info('demo.session_ready', ['session_id' => $this->id]);
    }

    public function markEnded(): void
    {
        $this->forceFill(['status' => DemoSessionStatus::Ended, 'ended_at' => now()])->save();
        Log::info('demo.session_ended', ['session_id' => $this->id]);
    }

    /** Its preparation failed: the run keeps why; the lock is freed for another try. */
    public function markFailed(): void
    {
        $this->forceFill(['status' => DemoSessionStatus::Failed, 'ended_at' => now()])->save();
        Log::warning('demo.session_failed', ['session_id' => $this->id]);
    }

    /** The moment the session's own activity starts: when it became ready (before that, when it started). */
    public function since(): CarbonImmutable
    {
        return $this->ready_at ?? $this->started_at;
    }

    /** The presenter opened the panel page `$routeName`; each page is kept once, even when two requests record it at once. */
    public function recordVisit(string $routeName): void
    {
        if (in_array($routeName, $this->visited, true)) {
            return;
        }

        $recorded = DB::update(
            'UPDATE demo_sessions SET visited = visited || jsonb_build_array(?::text) WHERE id = ? AND NOT visited @> jsonb_build_array(?::text)',
            [$routeName, $this->id, $routeName],
        );
        $this->visited = [...$this->visited, $routeName];
        $this->syncOriginalAttribute('visited');

        if ($recorded > 0) {
            Log::info('demo.session_page_visited', ['session_id' => $this->id, 'route' => $routeName]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tours' => 'array',
            'skipped_steps' => 'array',
            'visited' => 'array',
            'status' => DemoSessionStatus::class,
            'started_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
