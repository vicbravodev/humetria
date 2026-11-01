<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\TrainingStanding;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * When each person's yearly training falls due (numeral 5.9, criterio C-24): twelve months after the last training they
 * attended in their work center, or after they joined the roster if they never attended one. Calendar days in the
 * company's time zone; a month that is shorter ends on its last day (29 Feb + 12 months is 28 Feb). The warning goes out
 * one month before (the 11-month notice).
 */
final readonly class TrainingDue
{
    private const int MONTHS = 12;

    public function __construct(private CurrentTenant $tenant) {}

    public function dueOn(Teleworker $teleworker): CarbonImmutable
    {
        return $this->schedule([$teleworker])[$teleworker->id]['due'];
    }

    /** The day the 11-month warning of this person is sent. */
    public function warnOn(Teleworker $teleworker): CarbonImmutable
    {
        return $this->warningOf($this->dueOn($teleworker));
    }

    public function warningOf(CarbonImmutable $due): CarbonImmutable
    {
        return $due->subMonthsNoOverflow(1);
    }

    /**
     * Where a person stands on `$today`: past the due day, within the month before it, or up to date.
     */
    public function standing(CarbonImmutable $due, CarbonImmutable $today): TrainingStanding
    {
        return match (true) {
            $today->greaterThan($due) => TrainingStanding::Overdue,
            $today->greaterThanOrEqualTo($this->warningOf($due)) => TrainingStanding::DueSoon,
            default => TrainingStanding::Ok,
        };
    }

    /**
     * For each person (by teleworker id) the last training they attended in their center during the current period, and
     * the day the next one is due. Only trainings held on or after the day the period started count: what a person
     * learned before teleworking is not training about their workplace (numeral 5.9). Without one, the due day counts
     * from the start of the period. One query per thousand people.
     *
     * @param  list<Teleworker>  $teleworkers
     * @return array<string, array{last: string|null, due: CarbonImmutable}>
     */
    public function schedule(array $teleworkers): array
    {
        $zone = config()->string('app.display_timezone');
        $last = $this->lastTrainings($teleworkers, $zone);
        $schedule = [];

        foreach ($teleworkers as $teleworker) {
            $trained = $last[$teleworker->id] ?? null;
            $base = $trained !== null
                ? CarbonImmutable::createFromFormat('Y-m-d', $trained, $zone)?->startOfDay()
                : ($teleworker->started_at ?? $teleworker->created_at)->setTimezone($zone)->startOfDay();
            $schedule[$teleworker->id] = ['last' => $trained, 'due' => ($base ?? CarbonImmutable::now($zone)->startOfDay())->addMonthsNoOverflow(self::MONTHS)];
        }

        return $schedule;
    }

    /**
     * @param  list<Teleworker>  $teleworkers
     * @return array<string, string> teleworker id => Y-m-d
     */
    private function lastTrainings(array $teleworkers, string $zone): array
    {
        $last = [];

        foreach (array_chunk(array_map(fn (Teleworker $teleworker): string => $teleworker->id, $teleworkers), 1000) as $ids) {
            $rows = DB::table('teleworkers as period')
                ->join('training_attendees as attendee', fn (JoinClause $join) => $join->on('attendee.employee_id', '=', 'period.employee_id')->on('attendee.tenant_id', '=', 'period.tenant_id'))
                ->join('trainings as training', fn (JoinClause $join) => $join->on('training.id', '=', 'attendee.training_id')->on('training.work_center_id', '=', 'period.work_center_id'))
                ->where('period.tenant_id', $this->tenant->id())
                ->whereIn('period.id', $ids)
                ->whereRaw('training.held_on >= (coalesce(period.started_at, period.created_at) at time zone ?)::date', [$zone])
                ->groupBy('period.id')
                ->selectRaw('period.id, max(training.held_on) as held_on')
                ->get();

            foreach ($rows as $row) {
                if (is_string($row->id) && is_string($row->held_on)) {
                    $last[$row->id] = $row->held_on;
                }
            }
        }

        return $last;
    }

    /**
     * The 8.1 topics no training of the center covered in the last twelve months, in the norm's order.
     *
     * @return list<TrainingTopic>
     */
    public function uncoveredTopics(string $workCenterId, CarbonImmutable $today): array
    {
        $covered = Training::query()->where('work_center_id', $workCenterId)->where('held_on', '>=', $today->subMonthsNoOverflow(self::MONTHS)->toDateString())
            ->get(['id', 'topics'])->flatMap(fn (Training $training): array => $training->topics)->unique()->all();

        return array_values(array_filter(TrainingTopic::cases(), fn (TrainingTopic $topic): bool => ! in_array($topic, $covered, true)));
    }
}
