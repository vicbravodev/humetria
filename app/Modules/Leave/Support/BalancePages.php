<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Models\LeaveTermination;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Contracts\Seniority;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The days of everybody: per person, their policy, what they can still ask for, what they were given in advance and when
 * their next period comes, with a tab for what RH has to chase (no start date, a statement nobody confirmed, a person who
 * left with days to settle). Days only, never an amount. The tabs count the whole company, whatever is searched.
 *
 * @phpstan-type Row array{id: string, name: string, active: bool, policy: string, available: int, waiting: int, advanced: int, granted: int|null, nextAnniversary: string|null}
 */
final readonly class BalancePages
{
    public const int PER_PAGE = 10;

    /** The tabs, in the order of what asks for action; it opens on the first that has someone, or on everybody. */
    public const array TABS = ['statements', 'missing', 'all', 'terminations'];

    public function __construct(private Roster $roster, private Seniority $seniority, private PolicyResolver $policies, private GrantEntitlements $grants) {}

    /**
     * @return array{tab: string, q: string, today: string, owedDays: int, counts: array{all: int, missing: int, statements: int, terminations: int}, people: LengthAwarePaginator<int, Row>}
     */
    public function index(string $tab, string $search): array
    {
        $ids = [
            'missing' => $this->grants->missingStartDate(),
            'statements' => self::ids(LeaveStatement::query()->whereNull('acknowledged_at')),
            'terminations' => self::ids(LeaveTermination::query()->whereNull('settled_at')->whereNull('voided_at')),
        ];
        // ponytail: counts the roster by its ids; a count query in Roster if companies reach tens of thousands.
        $counts = ['all' => count($this->roster->matchingIds(new RosterQuery)), 'missing' => count($ids['missing']), 'statements' => count($ids['statements']), 'terminations' => count($ids['terminations'])];

        $tab = in_array($tab, self::TABS, true) ? $tab : self::opening($counts);

        return [
            'tab' => $tab,
            'q' => $search,
            'today' => PeriodWindow::today()->toDateString(),
            'owedDays' => (int) LeaveEntry::query()->whereNotNull('leave_entitlement_id')->sum('days'),
            'counts' => $counts,
            'people' => $this->page(new RosterQuery(employeeIds: $ids[$tab] ?? null, search: $search)),
        ];
    }

    /**
     * What it opens on: the first thing RH has to chase that has someone, or everybody.
     *
     * @param  array{all: int, missing: int, statements: int, terminations: int}  $counts
     */
    private static function opening(array $counts): string
    {
        return $counts['statements'] > 0 ? 'statements' : ($counts['missing'] > 0 ? 'missing' : 'all');
    }

    /**
     * @param  Builder<LeaveStatement>|Builder<LeaveTermination>  $query
     * @return list<string>
     */
    private static function ids(Builder $query): array
    {
        return array_values(array_unique(array_map(fn (LeaveStatement|LeaveTermination $row): string => $row->employee_id, $query->orderBy('employee_id')->get(['employee_id'])->all())));
    }

    /**
     * @return LengthAwarePaginator<int, Row>
     */
    private function page(RosterQuery $query): LengthAwarePaginator
    {
        $page = $this->roster->page($query, self::PER_PAGE);
        $ids = array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items()));
        $terms = $this->policies->for($ids);
        $periods = Balances::of($ids);
        $advanced = Balances::advanced($ids);
        $waiting = Balances::waiting($ids);
        $starts = $this->seniority->startDatesFor($ids);
        $today = PeriodWindow::today();

        return $page->through(fn (RosterEntry $person): array => [
            'id' => $person->id,
            'name' => $person->name,
            'active' => $person->active,
            'policy' => ($terms[$person->id] ?? null)->name ?? 'Ley',
            'available' => array_sum(array_map(fn (PeriodBalance $period): int => $period->left(), $periods[$person->id] ?? [])) + ($advanced[$person->id] ?? 0),
            'waiting' => $waiting[$person->id] ?? 0,
            'advanced' => -($advanced[$person->id] ?? 0),
            'granted' => self::granted($periods[$person->id] ?? []),
            'nextAnniversary' => isset($starts[$person->id]) ? self::next($starts[$person->id], $today)->toDateString() : null,
        ]);
    }

    /**
     * The days given in the periods that still hold days; null when there are none, which leaves a bar without a whole.
     *
     * @param  list<PeriodBalance>  $periods
     */
    private static function granted(array $periods): ?int
    {
        $granted = array_sum(array_map(fn (PeriodBalance $period): int => $period->left() > 0 ? $period->granted : 0, $periods));

        return $granted > 0 ? $granted : null;
    }

    /**
     * The first anniversary of the start after today (the first one, with a start still ahead).
     */
    private static function next(CarbonImmutable $start, CarbonImmutable $today): CarbonImmutable
    {
        $years = max(1, $today->year - $start->year);
        $next = $start->addYearsNoOverflow($years);

        return $next->greaterThan($today) ? $next : $start->addYearsNoOverflow($years + 1);
    }
}
