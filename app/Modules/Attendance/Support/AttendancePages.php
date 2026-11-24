<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\WeekSummary;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\OverLimit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Leave\Contracts\Absences;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's overview of the registro sends to the browser: one week (the last that ended unless another is asked for,
 * never one to come) of the people active in the company's centers in use, or in one of them. How many are al día,
 * objetadas, sin confirmar and near the year's limits, the correction requests waiting and the centers with no zone;
 * then the people of one state, 25 at a time, searched by name, with their hours and the limits they broke, and the
 * centers 25 at a time. Which days a person was away with vacations or a leave, never which of the two. What a person
 * disputed and RH's note once resolved; a person who left stays in view while their week is disputed. Never a position,
 * a coordinate or a device.
 */
final readonly class AttendancePages
{
    private const int PER_PAGE = 25;

    /** The tabs of the list: every person, each answer, and the weeks at risk. */
    private const array VIEWS = ['all', 'pending', 'disputed', 'confirmed', 'at_risk'];

    public function __construct(private Roster $roster, private WorkCenters $workCenters, private SetupProgress $setup, private Absences $absences, private Timezones $timezones) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $week, string $view, string $search): array
    {
        // The week of the center asked for, in its zone; of the whole company, in the company's.
        $period = $this->week($week, $center === null ? $this->timezones->forTenant() : $this->timezones->forCenter($center->id));
        $panel = PanelScope::of($this->workCenters, $this->roster, $center);
        $names = $panel->names;
        $scope = $center === null ? array_keys($names) : [$center->id];
        // Every answer of the week in the company: a person who left keeps their disputed week in view until RH resolves it.
        $answers = WeeklyConfirmation::query()->where('week_start', $period->start()->toDateString())
            ->get(['id', 'employee_id', 'status', 'reason', 'resolution_note'])->keyBy('employee_id');
        $people = [...$panel->people, ...$this->leftWithDispute($answers, $panel->people, $scope)];
        [$summaries, $worked] = TeamWeek::withDays($people, $period);
        $states = [];
        foreach ($people as $id) {
            $states[$id] = $answers->get($id)->status->value ?? WeekStatus::Pending->value;
        }
        $atRisk = array_keys(array_filter($summaries, fn ($summary): bool => TeamWeek::atRisk($summary, $period->start()->year)));
        $zones = AttendanceZone::query()->toBase()->selectRaw('work_center_id, count(*) as total')->groupBy('work_center_id')->pluck('total', 'work_center_id')->all();
        // A center in the field needs no zone.
        $field = AttendanceCenterMode::fieldCenters();
        $view = in_array($view, self::VIEWS, true) ? $view : 'all';
        $page = $this->roster->page(new RosterQuery(employeeIds: match ($view) {
            'all' => $people,
            'at_risk' => $atRisk,
            default => array_keys($states, $view, true),
        }, search: $search), self::PER_PAGE);
        // Who of the page has an agreed schedule: without one they are held to 8 h a day with no rest day. One query.
        $scheduled = array_flip(AttendanceSchedule::query()
            ->among('employee_id', array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items())))
            ->startedBy($period->end()->toDateString())
            ->get(['employee_id'])->map(fn (AttendanceSchedule $schedule): string => $schedule->employee_id)->all());
        // The days each person of the page was away with vacations or a leave: not days they missed. One query.
        $away = AbsentDays::of($this->absences->between($period->start(), $period->end(), array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items()))), $period);

        return [
            'week' => [
                'start' => $period->start()->toDateString(),
                'end' => $period->end()->toDateString(),
                'over' => $period->isOver(),
                'previous' => $period->start()->subWeek()->toDateString(),
                'next' => $period->isOver() ? $period->start()->addWeek()->toDateString() : null,
            ],
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => [
                'all' => count($people),
                ...array_combine(
                    array_map(fn (WeekStatus $status): string => $status->value, WeekStatus::cases()),
                    array_map(fn (WeekStatus $status): int => count(array_keys($states, $status->value, true)), WeekStatus::cases()),
                ),
                'at_risk' => count($atRisk),
            ],
            'pendingCorrections' => CorrectionRequest::query()->where('status', CorrectionStatus::Pending)->count(),
            'centersWithoutZones' => count(array_diff($scope, array_keys($zones), array_keys($field))),
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $names[$person->workCenterId] ?? '—',
                'ordinaryMinutes' => $summaries[$person->id]->ordinaryMinutes ?? 0,
                'overtimeMinutes' => $summaries[$person->id]->overtimeMinutes ?? 0,
                'flags' => array_map(fn (OverLimit $flag): string => $flag->value, $summaries[$person->id]->flags ?? []),
                'status' => $states[$person->id] ?? WeekStatus::Pending->value,
                'atRisk' => in_array($person->id, $atRisk, true),
                'hasSchedule' => isset($scheduled[$person->id]),
                'days' => $this->ribbon($worked[$person->id] ?? [], $summaries[$person->id]->overtimeByDay ?? [], $period),
                'absentDays' => array_keys($away[$person->id] ?? []),
                'active' => $person->active,
                'dispute' => self::dispute($answers->get($person->id)),
            ]),
            'centers' => $this->centers($panel->byCenter, $zones, $field),
            'setup' => $this->setup->of($names, $panel->byCenter),
            'hours' => [
                'ordinaryMinutes' => array_sum(array_map(fn (WeekSummary $summary): int => $summary->ordinaryMinutes, $summaries)),
                'overtimeMinutes' => array_sum(array_map(fn (WeekSummary $summary): int => $summary->overtimeMinutes, $summaries)),
            ],
            'canManage' => Gate::allows('manage-attendance'),
        ];
    }

    /**
     * The people who left whose week, in these centers, is disputed: one query when there are any, none otherwise.
     *
     * @param  Collection<string, WeeklyConfirmation>  $answers  by employee id
     * @param  list<string>  $active  the people active in scope
     * @param  list<string>  $scope  the centers in view
     * @return list<string>
     */
    private function leftWithDispute(Collection $answers, array $active, array $scope): array
    {
        $disputed = array_values(array_diff($answers->where('status', WeekStatus::Disputed)->keys()->all(), $active));

        if ($disputed === []) {
            return [];
        }

        return array_values(array_map(
            fn (RosterEntry $person): string => $person->id,
            array_filter($this->roster->findMany($disputed), fn (RosterEntry $person): bool => ! $person->active && in_array($person->workCenterId, $scope, true)),
        ));
    }

    /**
     * What the person disputed and, once RH resolved it, RH's note; null for a week never disputed.
     *
     * @return array{id: string, reason: string, resolution: string|null}|null
     */
    private static function dispute(?WeeklyConfirmation $answer): ?array
    {
        return $answer?->reason === null ? null : ['id' => $answer->id, 'reason' => $answer->reason, 'resolution' => $answer->resolution_note];
    }

    /**
     * The road to the first mark of the whole company, whatever center the overview is narrowed to.
     *
     * @return array<string, mixed>
     */
    public function setup(): array
    {
        $panel = PanelScope::of($this->workCenters, $this->roster, null);

        return ['setup' => $this->setup->of($panel->names, $panel->byCenter), 'canManage' => Gate::allows('manage-attendance')];
    }

    /**
     * The seven days of a person's week for the ribbon: the minutes worked and, of them, the overtime.
     *
     * @param  list<int>  $worked  minutes worked each day, Monday to Sunday
     * @param  array<string, int>  $overtimeByDay  overtime minutes by date (Y-m-d)
     * @return list<array{worked: int, overtime: int}>
     */
    private function ribbon(array $worked, array $overtimeByDay, WeekPeriod $period): array
    {
        return array_map(fn (int $offset): array => [
            'worked' => $worked[$offset] ?? 0,
            'overtime' => $overtimeByDay[$period->start()->addDays($offset)->toDateString()] ?? 0,
        ], range(0, 6));
    }

    /**
     * A center of the registro: its active people and its zones by name, 25 at a time. Where each zone is (its center) only
     * for whoever may change it: a viewer reads its name and its radius.
     *
     * @return array<string, mixed>
     */
    public function center(WorkCenterSummary $center): array
    {
        $manage = Gate::allows('manage-attendance');
        $policy = CompanyPolicy::current();
        $timezone = $this->timezones->forCenter($center->id);

        return [
            'center' => ['id' => $center->id, 'name' => $center->name, 'mainActivity' => $center->mainActivity],
            // The zone its days, shifts and lateness are measured in, always in view (design of time zones, §3.2).
            'timezone' => ['value' => $timezone, 'label' => $this->timezones->label($timezone)],
            'mode' => AttendanceCenterMode::isField($center->id) ? 'field' : 'company',
            // Where the map of a new zone opens; only whoever may add one needs it.
            'centerAddress' => $manage ? $this->workCenters->profile($center->id)?->address : null,
            'qrSeconds' => config()->integer('attendance.qr.window_seconds'),
            'requiresQr' => $policy->require_qr_onsite,
            'tolerance' => $policy->max_accuracy_m,
            'screens' => KioskDevice::query()->where('work_center_id', $center->id)->whereNotNull('last_seen_at')->whereNull('revoked_at')->count(),
            'people' => count($this->roster->activeIdsIn($center->id)),
            'zones' => AttendanceZone::query()->where('work_center_id', $center->id)->orderBy('name')->orderBy('id')
                ->paginate(self::PER_PAGE, pageName: 'zones')->withQueryString()
                ->through(fn (AttendanceZone $zone): array => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'radius' => $zone->radius_m,
                    ...($manage ? ['latitude' => $zone->latitude, 'longitude' => $zone->longitude] : []),
                ]),
            'kiosks' => $this->kiosks($center->id, $timezone),
            'canManage' => $manage,
        ];
    }

    /**
     * A center's QR screens, the ones not revoked first, newest first: «issued» until one shows the code (a screen opened but
     * never seen may have been opened by a link preview), «active» once seen, «revoked». Local times; never a token.
     *
     * @return LengthAwarePaginator<int, array{id: string, state: string, issuedAt: string, lastSeenAt: string|null, revokedAt: string|null}>
     */
    private function kiosks(string $centerId, string $zone): LengthAwarePaginator
    {
        $local = fn (?CarbonImmutable $at): ?string => $at?->setTimezone($zone)->format('Y-m-d H:i');

        return KioskDevice::query()->where('work_center_id', $centerId)
            ->orderByRaw('revoked_at is not null')->orderByDesc('created_at')->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'created_at', 'last_seen_at', 'revoked_at'], 'kiosks')->withQueryString()
            ->through(fn (KioskDevice $kiosk): array => [
                'id' => $kiosk->id,
                'state' => match (true) {
                    $kiosk->revoked_at !== null => 'revoked',
                    $kiosk->last_seen_at !== null => 'active',
                    default => 'issued',
                },
                'issuedAt' => $kiosk->created_at->setTimezone($zone)->format('Y-m-d H:i'),
                'lastSeenAt' => $local($kiosk->last_seen_at),
                'revokedAt' => $local($kiosk->revoked_at),
            ]);
    }

    /**
     * The centers in use, 25 at a time: their active people, their zones, the screens that show their code and how they mark.
     *
     * @param  array<string, list<string>>  $byCenter
     * @param  array<array-key, mixed>  $zones
     * @param  array<string, true>  $field  the centers that mark in the field
     * @return LengthAwarePaginator<int, array{id: string, name: string, people: int, zones: int, screens: int, mode: string}>
     */
    private function centers(array $byCenter, array $zones, array $field): LengthAwarePaginator
    {
        $page = $this->workCenters->activePage(self::PER_PAGE, 'centers');
        $ids = array_map(fn (WorkCenterSummary $summary): string => $summary->id, $page->items());
        $screens = KioskDevice::query()->toBase()->whereIn('work_center_id', $ids)->whereNotNull('last_seen_at')->whereNull('revoked_at')
            ->selectRaw('work_center_id, count(*) as total')->groupBy('work_center_id')->pluck('total', 'work_center_id')->all();

        return $page->through(fn (WorkCenterSummary $summary): array => [
            'id' => $summary->id,
            'name' => $summary->name,
            'people' => count($byCenter[$summary->id] ?? []),
            'zones' => is_int($zones[$summary->id] ?? null) ? $zones[$summary->id] : 0,
            'screens' => is_int($screens[$summary->id] ?? null) ? $screens[$summary->id] : 0,
            'mode' => isset($field[$summary->id]) ? 'field' : 'company',
        ]);
    }

    /** The week asked for by any of its days, the last one that ended by default; one to come is the week in course. */
    private function week(string $day, string $timezone): WeekPeriod
    {
        $current = WeekPeriod::containing(CarbonImmutable::now(), $timezone);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $day, $timezone) : null;

        // createFromFormat rolls 2026-02-30 over to March: only a date that reads back the same is one.
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $day) {
            return WeekPeriod::ofDate($current->start()->subWeek(), $timezone);
        }

        $asked = WeekPeriod::ofDate($date, $timezone);

        return $asked->start()->greaterThan($current->start()) ? $current : $asked;
    }
}
