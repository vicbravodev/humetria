<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Actions\BuildAuthorityReport;
use App\Modules\Attendance\Data\AuthorityRow;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;

/**
 * What the report's page sends to the browser: the center and the period asked for (the last week that ended by default),
 * its figures over every person-day, and the people with days in it, 25 at a time, searched by name, each with their days.
 * A period too long for the screen gets only the link to its CSV and how long it is. Never a position, a coordinate or a
 * device.
 */
final readonly class ReportPages
{
    private const int PER_PAGE = 25;

    public function __construct(private BuildAuthorityReport $report, private Roster $roster) {}

    /**
     * The period of the page when none was asked for: the last week that ended.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public static function lastWeek(): array
    {
        $week = WeekPeriod::ofDate(WeekPeriod::containing(CarbonImmutable::now())->start()->subWeek());

        return [$week->start(), $week->end()->startOfDay()];
    }

    /**
     * The periods RH asks for most, in the display time zone: this week up to today, the last week that ended and the last
     * calendar month.
     *
     * @return list<array{key: string, label: string, from: string, to: string}>
     */
    public static function presets(): array
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        [$weekFrom, $weekTo] = self::lastWeek();
        $month = $today->startOfMonth()->subMonthNoOverflow();

        return [
            ['key' => 'this-week', 'label' => 'Esta semana', 'from' => WeekPeriod::containing($today)->start()->toDateString(), 'to' => $today->toDateString()],
            ['key' => 'last-week', 'label' => 'Semana pasada', 'from' => $weekFrom->toDateString(), 'to' => $weekTo->toDateString()],
            ['key' => 'last-month', 'label' => 'Último mes', 'from' => $month->toDateString(), 'to' => $month->endOfMonth()->toDateString()],
        ];
    }

    /**
     * @param  int|null  $longPeriod  the days of a period too long for the screen; null to show it
     * @return array<string, mixed>
     */
    public function show(string $slug, ?WorkCenterSummary $center, CarbonImmutable $from, CarbonImmutable $to, string $search, ?int $longPeriod = null, string $show = 'all'): array
    {
        $page = [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'presets' => self::presets(),
            'limits' => ['screenDays' => config()->integer('attendance.report.page_max_days'), 'exportDays' => config()->integer('attendance.report.export_max_days')],
            'filters' => ['q' => $search, 'show' => $show],
            'summary' => null,
            'people' => null,
            'generatedAt' => null,
            'exportUrl' => null,
            'longPeriod' => null,
        ];

        if ($center !== null && $longPeriod !== null) {
            return [...$page, 'longPeriod' => ['days' => $longPeriod, 'maxOnScreen' => config()->integer('attendance.report.page_max_days')], 'exportUrl' => self::exportUrl($slug, $center, $from, $to)];
        }

        return $center === null ? $page : [...$page, ...$this->report($slug, $center, $from, $to, $search, $show)];
    }

    /**
     * The person-days a page built by show() counts: 0 before a center is chosen.
     *
     * @param  array<string, mixed>  $page
     */
    public function days(array $page): int
    {
        $summary = $page['summary'] ?? null;

        return is_array($summary) && is_int($summary['days'] ?? null) ? $summary['days'] : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $slug, WorkCenterSummary $center, CarbonImmutable $from, CarbonImmutable $to, string $search, string $show): array
    {
        $ids = $this->report->people($center->id, $from, $to);
        $totals = ['days' => 0, 'workedMinutes' => 0, 'confirmed' => 0, 'withCorrections' => 0, 'unconfirmed' => 0, 'ordinary' => 0, 'overtime' => 0, 'overTwelve' => 0, 'withoutEnd' => 0];
        $days = array_fill_keys($ids, []);
        $flagged = [];
        $unconfirmed = [];

        // ponytail: every person-day of the period is computed for the figures, a chunk of people at a time; a year of a
        // center of thousands takes seconds. Keep daily totals if RH opens such reports often.
        foreach ($this->report->rowsOf($ids, $center->id, $from, $to) as $row) {
            $totals['days']++;
            $totals['workedMinutes'] += $row->workedMinutes;
            $totals['ordinary'] += $row->ordinaryMinutes;
            $totals['overtime'] += $row->doubleMinutes + $row->tripleMinutes;
            $totals['confirmed'] += $row->confirmation === 'confirmado' ? 1 : 0;
            $totals['withCorrections'] += $row->corrections === [] ? 0 : 1;
            $totals['unconfirmed'] += $row->confirmation === 'sin confirmar' ? 1 : 0;
            $totals['overTwelve'] += $row->workedMinutes > LegalLimits::DAY_TOTAL_MAX ? 1 : 0;
            $totals['withoutEnd'] += in_array(BuildAuthorityReport::MISSING_END, $row->flags, true) ? 1 : 0;
            $flagged[$row->employeeId] = ($flagged[$row->employeeId] ?? false) || array_diff($row->flags, [BuildAuthorityReport::IN_PROGRESS]) !== [];
            $unconfirmed[$row->employeeId] = ($unconfirmed[$row->employeeId] ?? false) || $row->confirmation === 'sin confirmar';
            $days[$row->employeeId][] = self::day($row);
        }

        $listed = match ($show) {
            'flagged' => array_values(array_filter($ids, fn (string $id): bool => $flagged[$id] ?? false)),
            'unconfirmed' => array_values(array_filter($ids, fn (string $id): bool => $unconfirmed[$id] ?? false)),
            default => $ids,
        };
        $people = $this->roster->page(new RosterQuery(employeeIds: $listed, search: $search), self::PER_PAGE);

        $now = CarbonImmutable::now();

        return [
            'summary' => [
                'people' => count($ids),
                'days' => $totals['days'],
                'workedMinutes' => $totals['workedMinutes'],
                'confirmedPercent' => $totals['days'] === 0 ? 0 : intdiv($totals['confirmed'] * 100 + intdiv($totals['days'], 2), $totals['days']),
                'withCorrections' => $totals['withCorrections'],
                'unconfirmed' => $totals['unconfirmed'],
                'confirmedDays' => $totals['confirmed'],
                'ordinaryMinutes' => $totals['ordinary'],
                'overtimeMinutes' => $totals['overtime'],
                'overTwelveDays' => $totals['overTwelve'],
                'withoutEndDays' => $totals['withoutEnd'],
                'flaggedPeople' => count(array_filter($flagged)),
                'unconfirmedPeople' => count(array_filter($unconfirmed)),
            ],
            'people' => $people->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'days' => $days[$person->id] ?? [],
            ]),
            'generatedAt' => [
                'utc' => $now->utc()->format('Y-m-d H:i'),
                'local' => $now->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d H:i'),
            ],
            'exportUrl' => self::exportUrl($slug, $center, $from, $to),
        ];
    }

    private static function exportUrl(string $slug, WorkCenterSummary $center, CarbonImmutable $from, CarbonImmutable $to): string
    {
        return route('tenant.attendance.report.export', [$slug, 'center' => $center->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function day(AuthorityRow $row): array
    {
        return [
            'date' => $row->localDate,
            'start' => $row->start,
            'end' => $row->end,
            'endDate' => $row->endDate,
            'breakMinutes' => $row->breakMinutes,
            'workedMinutes' => $row->workedMinutes,
            'ordinaryMinutes' => $row->ordinaryMinutes,
            'doubleMinutes' => $row->doubleMinutes,
            'tripleMinutes' => $row->tripleMinutes,
            'restDayMinutes' => $row->restDayMinutes,
            'evidence' => $row->evidence,
            'corrections' => $row->corrections,
            'confirmation' => $row->confirmation,
            'flags' => $row->flags,
        ];
    }
}
