<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\TrainingStanding;
use App\Modules\Telework\Enums\TrainingTopic;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
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
        return $this->dueDates([$teleworker])[$teleworker->id];
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
     * The due day of each person, by teleworker id, with one query for all of them.
     *
     * @param  list<Teleworker>  $teleworkers
     * @return array<string, CarbonImmutable>
     */
    public function dueDates(array $teleworkers): array
    {
        $zone = config()->string('app.display_timezone');
        $last = $this->lastTrainings($teleworkers);
        $due = [];

        foreach ($teleworkers as $teleworker) {
            $trained = $last[$teleworker->id] ?? null;
            $base = $trained !== null
                ? CarbonImmutable::createFromFormat('Y-m-d', $trained, $zone)?->startOfDay()
                : ($teleworker->started_at ?? $teleworker->created_at)->setTimezone($zone)->startOfDay();
            $due[$teleworker->id] = ($base ?? CarbonImmutable::now($zone)->startOfDay())->addMonthsNoOverflow(self::MONTHS);
        }

        return $due;
    }

    /**
     * The day of the last training each person attended in their center, Y-m-d, by teleworker id (absent: none).
     *
     * @param  list<Teleworker>  $teleworkers
     * @return array<string, string>
     */
    public function lastTrainings(array $teleworkers): array
    {
        if ($teleworkers === []) {
            return [];
        }

        $rows = DB::table('training_attendees as attendee')
            ->join('trainings as training', 'training.id', '=', 'attendee.training_id')
            ->where('attendee.tenant_id', $this->tenant->id())
            ->whereIn('attendee.employee_id', array_map(fn (Teleworker $teleworker): string => $teleworker->employee_id, $teleworkers))
            ->groupBy('attendee.employee_id', 'training.work_center_id')
            ->selectRaw('attendee.employee_id, training.work_center_id, max(training.held_on) as held_on')
            ->get();
        $latest = [];

        foreach ($rows as $row) {
            if (is_string($row->employee_id) && is_string($row->work_center_id) && is_string($row->held_on)) {
                $latest["{$row->employee_id}|{$row->work_center_id}"] = $row->held_on;
            }
        }

        $last = [];

        foreach ($teleworkers as $teleworker) {
            $day = $latest["{$teleworker->employee_id}|{$teleworker->work_center_id}"] ?? null;

            if ($day !== null) {
                $last[$teleworker->id] = $day;
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
