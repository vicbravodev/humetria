<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Data\AuthorityRow;
use App\Modules\Attendance\Data\DayHours;
use App\Modules\Attendance\Data\WeekSummary;
use App\Modules\Attendance\Enums\EvidenceLevel;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\CompanyPolicy;
use App\Modules\Attendance\Support\CorrectionHistory;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\HoursCalculator;
use App\Modules\Attendance\Support\LegalLimits;
use App\Modules\Attendance\Support\ScheduleTimeline;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Attendance\Support\WorkedDay;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use LogicException;

/**
 * The report for the authority (LFT art. 132-XXXIV, criterio 6.1): every day of every person in one work center over a
 * period of local dates, both included. A day belongs to the local date of its start and to the center of its start, so a
 * person who moved appears in each center only for the days started there; deactivated people with days are in it. Only
 * days with a mark: a scheduled day with none does not appear. The hours are split as «Mi semana» splits them, week by
 * week from its Monday even when the period starts later; the week's double-pay cap goes to its earliest overtime, day
 * by day, and the rest is triple.
 *
 * A day of approved vacations or leave (Vacaciones) says which of the two, never the type of the leave (C-22), has no
 * lateness (criterio 2.6) and, when the person marked in it, a flag: vacations worked are RH's to notice (art. 81).
 *
 * A day with a mark made without a connection says so (criterio 6.1), with the key that signed it, even after RH corrected
 * it (the root of its chain of corrections says); says it still waits for RH's review, or that RH verified it.
 *
 * Streamed: the people go in chunks, each read in the same nine queries (marks in two, what was corrected, the origin of the
 * marks, schedules, answers, absences, and the roster's two), so memory holds one chunk.
 */
final readonly class BuildAuthorityReport
{
    /** The flag of a day that started and never ended; the page counts them. */
    public const string MISSING_END = 'Sin marca de fin';

    /** A day with a mark taken in a field center. */
    public const string FIELD = 'Marcó en campo';

    /** The flag of today's day with its start and no end yet: information, not a warning. */
    public const string IN_PROGRESS = 'En curso';

    /** A day the person marked while away with approved vacations or leave. */
    public const string MARKED_WHILE_AWAY = 'Marcó durante una ausencia';

    /** A day with a mark made without a connection, or one RH corrected that was; the key that signed it goes after. */
    public const string OFFLINE = 'Marcó sin conexión';

    /** A day with a mark made without a connection that RH has not reviewed. */
    public const string TO_VERIFY = 'Con marcas sin conexión por verificar';

    /** A day with a mark made without a connection whose review in force is «verified». */
    public const string VERIFIED = 'Marca sin conexión verificada por RH';

    private const array SIGNED_WITH = [
        SignerType::Passkey->value => 'firmó con su llave de acceso',
        SignerType::DeviceKey->value => 'con la llave del dispositivo',
    ];

    /** At most the people of attendance.report.chunk_size per chunk, and at most this many person-weeks of marks held at once: a long period takes fewer people per chunk. */
    private const int PERSON_WEEKS = 1000;

    private const array EVIDENCE = [
        EvidenceLevel::Strong->value => 'Fuerte',
        EvidenceLevel::Medium->value => 'Media',
        EvidenceLevel::Basic->value => 'Básica',
        EvidenceLevel::Corrected->value => 'Corregida',
    ];

    /** From the strongest evidence to the weakest. */
    private const array RANK = [
        EvidenceLevel::Strong->value => 0,
        EvidenceLevel::Medium->value => 1,
        EvidenceLevel::Basic->value => 2,
        EvidenceLevel::Corrected->value => 3,
    ];

    public function __construct(private Roster $roster, private Timezones $timezones, private Absences $absences) {}

    /**
     * @return LazyCollection<int, AuthorityRow> by person (by name) and date
     */
    public function handle(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): LazyCollection
    {
        return $this->rowsOf($this->people($workCenterId, $from, $to), $workCenterId, $from, $to);
    }

    /**
     * The people with a day started in the center in the period, by name: two queries, the starts of this module's table
     * and the roster's order.
     *
     * @return list<string>
     */
    public function people(string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $ids = AttendanceMark::query()
            ->where('work_center_id', $workCenterId)
            ->where('type', MarkType::Start)
            // A day wider on each side for the index, then the date each start had in its own zone.
            ->where('marked_at', '>=', CarbonImmutable::parse($from->toDateString(), 'UTC')->subDay())
            ->where('marked_at', '<', CarbonImmutable::parse($to->toDateString(), 'UTC')->addDays(2))
            ->whereRaw('(marked_at at time zone timezone)::date between ?::date and ?::date', [$from->toDateString(), $to->toDateString()])
            // A start corrected to another time counts at that time; a voided one still brings its person, with the void.
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_marks', 'corrections')
                ->whereColumn('corrections.tenant_id', 'attendance_marks.tenant_id')
                ->whereColumn('corrections.corrects_mark_id', 'attendance_marks.id')
                ->whereNotNull('corrections.marked_at'))
            ->distinct()
            ->pluck('employee_id')
            ->all();
        $ids = array_values(array_filter($ids, is_string(...)));

        return $ids === [] ? [] : $this->roster->matchingIds(new RosterQuery(employeeIds: $ids));
    }

    /**
     * The rows of these people, in their order.
     *
     * @param  list<string>  $employeeIds
     * @return LazyCollection<int, AuthorityRow>
     */
    public function rowsOf(array $employeeIds, string $workCenterId, CarbonImmutable $from, CarbonImmutable $to): LazyCollection
    {
        $period = [$from->toDateString(), $to->toDateString()];
        $timezone = $this->timezones->forCenter($workCenterId);
        $weeks = [WeekPeriod::ofDate($from, $timezone), WeekPeriod::ofDate($to, $timezone)];
        // A day of slack: a change of clock inside the period never loses a week.
        $count = intdiv($weeks[1]->start()->getTimestamp() - $weeks[0]->start()->getTimestamp() + 86400, 7 * 86400) + 1;
        $size = max(1, min(config()->integer('attendance.report.chunk_size'), intdiv(self::PERSON_WEEKS, $count)));

        return LazyCollection::make(function () use ($employeeIds, $workCenterId, $period, $weeks, $size) {
            foreach (array_chunk($employeeIds, $size) as $chunk) {
                foreach ($this->chunk($chunk, $workCenterId, $period, $weeks) as $row) {
                    yield $row;
                }
            }
        });
    }

    /**
     * @param  list<string>  $employeeIds
     * @param  array{string, string}  $period  its first and last local dates
     * @param  array{WeekPeriod, WeekPeriod}  $weeks  the weeks of those dates
     * @return list<AuthorityRow>
     */
    private function chunk(array $employeeIds, string $workCenterId, array $period, array $weeks): array
    {
        $byPerson = EffectiveMarks::byDayBetween($employeeIds, $weeks[0]->start(), $weeks[1]->end());
        // A day of slack each side: a night's end, or the end a void points to, may fall past the weeks.
        $offline = self::offlineMarks($byPerson);
        $history = CorrectionHistory::of($employeeIds, $weeks[0]->startUtc()->subDay(), $weeks[1]->nextStartUtc()->addDay());
        $schedules = ScheduleTimeline::of($employeeIds);
        $tolerance = $schedules->hasStartTimes() ? CompanyPolicy::current()->late_tolerance_minutes : 0;
        $answers = WeeklyConfirmation::query()->among('employee_id', $employeeIds)
            ->whereBetween('week_start', [$weeks[0]->start()->toDateString(), $weeks[1]->start()->toDateString()])
            ->get(['employee_id', 'week_start', 'status', 'digest'])
            ->keyBy(fn (WeeklyConfirmation $answer): string => $answer->employee_id.' '.$answer->week_start->toDateString());
        $away = $this->away($employeeIds, $weeks[0]->start(), $weeks[1]->end());
        $people = $this->roster->findMany($employeeIds);
        $rows = [];

        foreach ($employeeIds as $id) {
            $person = $people[$id] ?? null;

            if ($person === null) {
                continue;
            }

            [$days, $voids] = self::withVoids($byPerson[$id] ?? [], $history->voidsOf($id));

            foreach (self::byWeek($days) as $monday => $days) {
                $hours = array_map(
                    fn (int $offset): DayHours => WorkedDay::hours($date = CarbonImmutable::parse($monday)->addDays($offset), $days[$date->toDateString()] ?? [], $schedules->on($id, $date), $tolerance, isset($away[$id][$date->toDateString()])),
                    range(0, 6),
                );
                $summary = HoursCalculator::week($hours, $schedules->on($id, CarbonImmutable::parse($monday)->addDays(6))->shift_type ?? ShiftType::Day, CarbonImmutable::parse($monday)->year);
                $answer = $answers->get("{$id} {$monday}");
                $doubleLeft = LegalLimits::weeklyOvertimeCapMinutes(CarbonImmutable::parse($monday)->year);

                foreach ($hours as $day) {
                    $date = $day->date->toDateString();
                    $overtime = $summary->overtimeByDay[$date] ?? 0;
                    $double = min($overtime, $doubleLeft);
                    $doubleLeft -= $double;
                    $marks = $days[$date] ?? [];
                    $dayVoids = $voids[$date] ?? [];
                    $center = $marks === [] ? ($dayVoids[0]['center'] ?? null) : $marks[0]->work_center_id;

                    if (($marks !== [] || $dayVoids !== []) && $date >= $period[0] && $date <= $period[1] && $center === $workCenterId) {
                        $notes = [...$history->notesFor($marks), ...array_map(fn (array $void): array => [$void['at']->getTimestamp(), $void['text']], $dayVoids)];
                        usort($notes, fn (array $a, array $b): int => $a[0] <=> $b[0]);
                        $rows[] = self::row($person, $marks, $day, [$overtime, $double], $summary, $answer, array_column($notes, 1), $away[$id][$date] ?? null, $offline);
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Who of these people was away which day of the weeks, with vacations or leave: one query.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array<string, 'vacation'|'leave'>> by employee id and local date
     */
    private function away(array $employeeIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $away = [];

        foreach ($this->absences->between($from, $to, $employeeIds) as $absence) {
            for ($date = $absence->from->max($from); $date->lessThanOrEqualTo($absence->to->min($to)); $date = $date->addDay()) {
                $away[$absence->employeeId][$date->toDateString()] = $absence->kind;
            }
        }

        return $away;
    }

    /**
     * A person's days with the marks voided in them: a voided start on its own local date (its day, even with nothing
     * left), any other on the day it was part of: the last that opened before it, from the day before on. A day opens at
     * its first effective mark or at a start voided in it, whichever is earlier, so a night whose start and end were both
     * voided stays one day.
     *
     * @param  array<string, list<AttendanceMark>>  $days
     * @param  list<array{at: CarbonImmutable, type: string, center: string, timezone: string, text: string}>  $voids
     * @return array{array<string, list<AttendanceMark>>, array<string, list<array{at: CarbonImmutable, type: string, center: string, timezone: string, text: string}>>}
     */
    private static function withVoids(array $days, array $voids): array
    {
        $opens = [];

        foreach ($days as $day => $marks) {
            if (($first = $marks[0]->marked_at ?? null) !== null) {
                $opens[$day] = $first;
            }
        }
        foreach ($voids as $void) {
            if (CorrectionHistory::beginsDay($void['type'])) {
                $day = $void['at']->setTimezone($void['timezone'])->toDateString();
                $opens[$day] = isset($opens[$day]) ? $opens[$day]->min($void['at']) : $void['at'];
            }
        }
        ksort($opens);
        $byDate = [];

        foreach ($voids as $void) {
            $original = $void['at']->setTimezone($void['timezone']);
            $date = $original->toDateString();

            if (! CorrectionHistory::beginsDay($void['type'])) {
                foreach ($opens as $day => $opened) {
                    $date = $opened->lessThanOrEqualTo($void['at']) && $day >= $original->subDay()->toDateString() ? $day : $date;
                }
            }

            $byDate[$date][] = $void;
            $days[$date] ??= [];
        }

        ksort($days);

        return [$days, $byDate];
    }

    /**
     * A person's days by the Monday of their week, each week's days by date.
     *
     * @param  array<string, list<AttendanceMark>>  $days
     * @return array<string, array<string, list<AttendanceMark>>>
     */
    private static function byWeek(array $days): array
    {
        $weeks = [];
        foreach ($days as $date => $marks) {
            $weeks[CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY)->toDateString()][$date] = $marks;
        }

        return $weeks;
    }

    /**
     * @param  list<AttendanceMark>  $marks  the day's effective marks, in order of time; none when all were voided
     * @param  array{int, int}  $overtime  the day's overtime minutes and how many of them are paid double
     * @param  list<string>  $corrections  what was corrected or voided in the day, in order
     * @param  'vacation'|'leave'|null  $absence
     * @param  array<string, array{signer: string, decision: string|null}>  $offline  by mark id: see offlineMarks()
     */
    private static function row(RosterEntry $person, array $marks, DayHours $day, array $overtime, WeekSummary $week, ?WeeklyConfirmation $answer, array $corrections, ?string $absence, array $offline): AuthorityRow
    {
        $first = $marks[0] ?? null;
        $last = $marks === [] ? null : $marks[count($marks) - 1];
        // Each mark at the hour of the place it was taken.
        $local = fn (AttendanceMark $mark): CarbonImmutable => ($mark->marked_at ?? throw new LogicException("Mark [{$mark->id}] has no time."))->setTimezone($mark->timezone);
        $ended = $last?->type === MarkType::End;
        [$minutes, $double] = $overtime;
        $confirmed = $answer?->status === WeekStatus::Confirmed;

        return new AuthorityRow(
            employeeId: $person->id,
            personName: $person->name,
            jobTitle: $person->jobTitle,
            localDate: $day->date->toDateString(),
            start: $first?->type === MarkType::Start ? $local($first)->format('H:i') : null,
            end: $ended ? $local($last)->format('H:i') : null,
            endDate: $ended ? $local($last)->toDateString() : null,
            breakMinutes: WorkedDay::breakMinutes($marks),
            workedMinutes: $day->workedMinutes,
            ordinaryMinutes: $day->restDay ? 0 : $day->workedMinutes - $minutes,
            doubleMinutes: $double,
            tripleMinutes: $minutes - $double,
            restDayMinutes: $day->restDay ? $day->workedMinutes : 0,
            evidence: self::evidence($marks, $corrections !== []),
            corrections: $corrections,
            confirmation: match ($answer?->status) {
                WeekStatus::Confirmed => 'confirmado',
                WeekStatus::Disputed => 'objetada',
                default => 'sin confirmar',
            },
            weekDigest: $confirmed ? ($answer->digest ?? '') : '',
            flags: [...self::flags($marks, $day, $minutes, $minutes - $double, $week, $offline), ...($absence !== null && $marks !== [] ? [self::MARKED_WHILE_AWAY] : [])],
            lateMinutes: $day->lateMinutes,
            timezone: self::zoneOf($first, $ended ? $last : null),
            absence: $absence,
            marks: array_map(fn (AttendanceMark $mark): array => ['id' => $mark->id, 'type' => $mark->type->value, 'date' => $local($mark)->toDateString(), 'time' => $local($mark)->format('H:i')], $marks),
        );
    }

    /**
     * The zone of a day with the offset of its start and, when the end has another (the repeated hour), of its end: the
     * hour repeated by a change of time always carries its offset (design of time zones §3.8).
     */
    private static function zoneOf(?AttendanceMark $first, ?AttendanceMark $end): string
    {
        if ($first?->marked_at === null) {
            return '';
        }

        $offset = fn (CarbonImmutable $at): string => 'UTC'.str_replace('-', '−', $at->format('P'));
        $start = $offset($first->marked_at->setTimezone($first->timezone));
        $last = $end?->marked_at === null ? $start : $offset($end->marked_at->setTimezone($end->timezone));

        return ($last === $start ? $start : "{$start} a {$last}").' · '.$first->timezone;
    }

    /**
     * «Marcó sin conexión (firmó con su llave de acceso)»: with the key that signed each of the day's marks made without a
     * connection, once each.
     *
     * @param  array<array-key, array{signer: string, decision: string|null}>  $fromOffline
     */
    private static function offlineFlag(array $fromOffline): string
    {
        $signed = array_values(array_unique(array_map(fn (array $mark): string => self::SIGNED_WITH[$mark['signer']] ?? '', $fromOffline)));

        return self::OFFLINE.' ('.implode(' y ', array_filter($signed)).')';
    }

    /**
     * Of the marks that count, the ones made without a connection or that correct one (down their whole chain of
     * corrections: the root says who signed it), with the key that signed the original and where RH's review of the mark
     * itself stands (`verified`, `incorrect`, or none): one query for everyone.
     *
     * @param  array<string, array<string, list<AttendanceMark>>>  $byPerson
     * @return array<string, array{signer: string, decision: string|null}> by mark id
     */
    private static function offlineMarks(array $byPerson): array
    {
        $ids = [];
        foreach ($byPerson as $days) {
            foreach ($days as $marks) {
                foreach ($marks as $mark) {
                    $ids[] = $mark->id;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        $tenant = resolve(CurrentTenant::class)->id();
        $rows = DB::select(
            'with recursive chain (mark_id, current_id, corrects_id) as (
                select id, id, corrects_mark_id from attendance_marks where tenant_id = ? and id = any(?::uuid[])
                union all
                select chain.mark_id, marks.id, marks.corrects_mark_id from chain
                    join attendance_marks marks on marks.tenant_id = ? and marks.id = chain.corrects_id
            )
            select chain.mark_id, deliveries.signer_type,
                (select reviews.decision from attendance_mark_reviews reviews where reviews.tenant_id = ? and reviews.mark_id = chain.mark_id order by reviews.seq desc limit 1) as decision
            from chain join attendance_offline_marks deliveries on deliveries.tenant_id = ? and deliveries.mark_id = chain.current_id
            where chain.corrects_id is null',
            [$tenant, '{'.implode(',', $ids).'}', $tenant, $tenant, $tenant],
        );
        $marks = [];

        foreach ($rows as $row) {
            if (is_object($row) && property_exists($row, 'mark_id') && is_string($row->mark_id) && property_exists($row, 'signer_type') && is_string($row->signer_type)) {
                $marks[$row->mark_id] = ['signer' => $row->signer_type, 'decision' => property_exists($row, 'decision') && is_string($row->decision) ? $row->decision : null];
            }
        }

        return $marks;
    }

    /**
     * The weakest evidence of the day's marks (criterio 5.3): a day is as strong as its weakest mark.
     *
     * @param  list<AttendanceMark>  $marks
     */
    private static function evidence(array $marks, bool $corrected): string
    {
        $weakest = $corrected ? EvidenceLevel::Corrected : EvidenceLevel::Strong;
        foreach ($marks as $mark) {
            $level = $mark->method->evidence();
            $weakest = self::RANK[$level->value] > self::RANK[$weakest->value] ? $level : $weakest;
        }

        return self::EVIDENCE[$weakest->value];
    }

    /**
     * @param  list<AttendanceMark>  $marks
     * @param  array<string, array{signer: string, decision: string|null}>  $offline
     * @return list<string>
     */
    private static function flags(array $marks, DayHours $day, int $overtime, int $triple, WeekSummary $week, array $offline): array
    {
        if ($marks === []) {
            return ['Sin marcas vigentes'];
        }

        // A day that started today, where it started, is still being worked (as «Mi semana» says): not missing its end yet.
        $today = CarbonImmutable::now($marks[0]->timezone)->toDateString();
        $open = WorkedDay::missingEnd($marks) && $marks[0]->type === MarkType::Start && $day->date->toDateString() === $today;

        $fromOffline = array_filter(array_map(fn (AttendanceMark $mark): ?array => $offline[$mark->id] ?? null, $marks));
        // Only a mark that is itself made without a connection waits for review (or was verified): a correction of one does not.
        $waiting = array_filter($marks, fn (AttendanceMark $mark): bool => in_array($mark->method, [MarkMethod::OfflineQr, MarkMethod::OfflineDeclared], true));

        return array_keys(array_filter([
            // Its own method (criterio 4.5): no zone nor QR was asked, the position proves where.
            self::FIELD => array_filter($marks, fn (AttendanceMark $mark): bool => in_array($mark->method, [MarkMethod::Field, MarkMethod::FieldUnlocated], true)) !== [],
            self::offlineFlag($fromOffline) => $fromOffline !== [],
            self::TO_VERIFY => array_filter($waiting, fn (AttendanceMark $mark): bool => ($offline[$mark->id]['decision'] ?? null) === null) !== [],
            self::VERIFIED => array_filter($waiting, fn (AttendanceMark $mark): bool => ($offline[$mark->id]['decision'] ?? null) === ReviewDecision::Verified->value) !== [],
            'Sin marca de inicio' => $marks[0]->type !== MarkType::Start,
            self::IN_PROGRESS => $open,
            self::MISSING_END => WorkedDay::missingEnd($marks) && ! $open,
            'Más de 12 h trabajadas en el día (art. 68)' => $day->workedMinutes > LegalLimits::DAY_TOTAL_MAX,
            'Más de 4 h extra en el día (art. 66)' => $overtime > LegalLimits::DAILY_OVERTIME_MAX,
            'Horas extra en más de 4 días de la semana (art. 66)' => $overtime > 0 && in_array(OverLimit::OvertimeDaysOver4, $week->flags, true),
            'Rebasó el tope de horas extra de la semana' => $triple > 0,
            'Más de 4 h triples en la semana (art. 68)' => $triple > 0 && in_array(OverLimit::TripleCapExceeded, $week->flags, true),
        ]));
    }
}
