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
     * @param  int|null  $longPeriod  the days of a period too long for the screen; null to show it
     * @return array<string, mixed>
     */
    public function show(string $slug, ?WorkCenterSummary $center, CarbonImmutable $from, CarbonImmutable $to, string $search, ?int $longPeriod = null): array
    {
        $page = [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filters' => ['q' => $search],
            'summary' => null,
            'people' => null,
            'generatedAt' => null,
            'exportUrl' => null,
            'longPeriod' => null,
        ];

        if ($center !== null && $longPeriod !== null) {
            return [...$page, 'longPeriod' => ['days' => $longPeriod, 'maxOnScreen' => config()->integer('attendance.report.page_max_days')], 'exportUrl' => self::exportUrl($slug, $center, $from, $to)];
        }

        return $center === null ? $page : [...$page, ...$this->report($slug, $center, $from, $to, $search)];
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
    private function report(string $slug, WorkCenterSummary $center, CarbonImmutable $from, CarbonImmutable $to, string $search): array
    {
        $ids = $this->report->people($center->id, $from, $to);
        $people = $this->roster->page(new RosterQuery(employeeIds: $ids, search: $search), self::PER_PAGE);
        $shown = array_fill_keys(array_map(fn (RosterEntry $person): string => $person->id, $people->items()), []);
        $totals = ['days' => 0, 'workedMinutes' => 0, 'confirmed' => 0, 'withCorrections' => 0, 'unconfirmed' => 0];

        // ponytail: every person-day of the period is computed for the figures, a chunk of people at a time; a year of a
        // center of thousands takes seconds. Keep daily totals if RH opens such reports often.
        foreach ($this->report->rowsOf($ids, $center->id, $from, $to) as $row) {
            $totals['days']++;
            $totals['workedMinutes'] += $row->workedMinutes;
            $totals['confirmed'] += $row->confirmation === 'confirmado' ? 1 : 0;
            $totals['withCorrections'] += $row->corrections === [] ? 0 : 1;
            $totals['unconfirmed'] += $row->confirmation === 'sin confirmar' ? 1 : 0;

            if (array_key_exists($row->employeeId, $shown)) {
                $shown[$row->employeeId][] = self::day($row);
            }
        }

        $now = CarbonImmutable::now();

        return [
            'summary' => [
                'people' => count($ids),
                'days' => $totals['days'],
                'workedMinutes' => $totals['workedMinutes'],
                'confirmedPercent' => $totals['days'] === 0 ? 0 : intdiv($totals['confirmed'] * 100 + intdiv($totals['days'], 2), $totals['days']),
                'withCorrections' => $totals['withCorrections'],
                'unconfirmed' => $totals['unconfirmed'],
            ],
            'people' => $people->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'days' => $shown[$person->id] ?? [],
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
