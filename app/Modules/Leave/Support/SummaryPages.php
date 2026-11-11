<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Actions\GrantEntitlements;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveEntry;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Organization\Contracts\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What RH reads first in Vacaciones: who is out, what waits for an answer, which windows are about to close and how many
 * days are still owed. Days and people only, never an amount; of a leave it says only that it is one (criterio C-22).
 */
final readonly class SummaryPages
{
    public const int PER_PAGE = 10;

    /** The views of the detail, the first is the default. */
    public const array VIEWS = ['out', 'windows', 'prescribable'];

    public function __construct(private Roster $roster, private GrantEntitlements $grants) {}

    /**
     * @return array{view: string, summary: array{outToday: int, outThisWeek: int, pending: int, escalated: int, windowsClosing: int, prescribableDays: int, liabilityDays: int, missingStartDate: int, statementsUnacknowledged: int}, counts: array{out: int, windows: int, prescribable: int}, people: LengthAwarePaginator<int, array{id: string, name: string, label: string, days: int, startsOn: string, endsOn: string}>}
     */
    public function index(string $requested): array
    {
        $view = in_array($requested, self::VIEWS, true) ? $requested : self::VIEWS[0];
        $today = PeriodWindow::today();
        $outToday = self::outBetween($today, $today)->distinct()->count('employee_id');
        $closing = self::closingPeriods($today)->count();

        return [
            'view' => $view,
            'summary' => [
                'outToday' => $outToday,
                'outThisWeek' => self::outBetween($today->startOfWeek(), $today->endOfWeek())->distinct()->count('employee_id'),
                'pending' => LeaveRequest::query()->where('status', RequestStatus::Requested)->whereNull('escalated_at')->count(),
                'escalated' => LeaveRequest::query()->where('status', RequestStatus::Requested)->whereNotNull('escalated_at')->count(),
                'windowsClosing' => $closing,
                // They stay in the balance: opposing the prescription is the company's decision (criterio C-11).
                'prescribableDays' => (int) LeaveEntry::query()->whereIn('leave_entitlement_id', LeaveEntitlement::query()->where('prescribes_on', '<', $today->toDateString())->select('id'))->sum('days'),
                'liabilityDays' => (int) LeaveEntry::query()->whereNotNull('leave_entitlement_id')->sum('days'),
                'missingStartDate' => count($this->grants->missingStartDate()),
                'statementsUnacknowledged' => LeaveStatement::query()->whereNull('acknowledged_at')->count(),
            ],
            'counts' => ['out' => $outToday, 'windows' => $closing, 'prescribable' => self::prescribablePeriods($today)->count()],
            'people' => match ($view) {
                'out' => $this->out($today),
                'windows' => $this->periods(self::closingPeriods($today)->orderBy('enjoy_by'), windows: true),
                default => $this->periods(self::prescribablePeriods($today)->orderBy('prescribes_on'), windows: false),
            },
        ];
    }

    /**
     * The requests approved or already enjoyed that touch the days from one to the other, both included.
     *
     * @return Builder<LeaveRequest>
     */
    private static function outBetween(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return LeaveRequest::query()
            ->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString());
    }

    /**
     * The periods that still have days: the sum of their entries is above zero.
     *
     * @return Builder<LeaveEntitlement>
     */
    private static function withDays(): Builder
    {
        return LeaveEntitlement::query()->whereRaw('(select coalesce(sum(days), 0) from leave_entries where leave_entries.leave_entitlement_id = leave_entitlements.id) > 0');
    }

    /**
     * Periods with days whose window to enjoy them closes within the warning days, or already closed.
     *
     * @return Builder<LeaveEntitlement>
     */
    private static function closingPeriods(CarbonImmutable $today): Builder
    {
        return self::withDays()->where('enjoy_by', '<=', $today->addDays(max(0, config()->integer('leave.enjoy_window_warning_days')))->toDateString());
    }

    /**
     * Periods with days that are past their eighteen months.
     *
     * @return Builder<LeaveEntitlement>
     */
    private static function prescribablePeriods(CarbonImmutable $today): Builder
    {
        return self::withDays()->where('prescribes_on', '<', $today->toDateString());
    }

    /**
     * Who is out today, the ones that left first.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, label: string, days: int, startsOn: string, endsOn: string}>
     */
    private function out(CarbonImmutable $today): LengthAwarePaginator
    {
        $page = self::outBetween($today, $today)->orderBy('starts_on')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        $people = $this->roster->findMany(array_values(array_unique(array_map(fn (LeaveRequest $request): string => $request->employee_id, $page->items()))));

        return $page->through(fn (LeaveRequest $request): array => [
            'id' => $request->id,
            'name' => ($people[$request->employee_id] ?? null)->name ?? '',
            'label' => $request->leave_type_id === null ? 'Vacaciones' : 'Permiso',
            'days' => $request->working_days,
            'startsOn' => $request->starts_on->toDateString(),
            'endsOn' => $request->ends_on->toDateString(),
        ]);
    }

    /**
     * A page of periods with what is left of each: from its anniversary to the end of its window, or to its eighteen months.
     *
     * @param  Builder<LeaveEntitlement>  $periods  already in the order of the view
     * @return LengthAwarePaginator<int, array{id: string, name: string, label: string, days: int, startsOn: string, endsOn: string}>
     */
    private function periods(Builder $periods, bool $windows): LengthAwarePaginator
    {
        $page = $periods->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        $ids = array_values(array_unique(array_map(fn (LeaveEntitlement $period): string => $period->employee_id, $page->items())));
        $people = $this->roster->findMany($ids);
        $balances = Balances::of($ids);

        return $page->through(function (LeaveEntitlement $period) use ($people, $balances, $windows): array {
            $left = 0;

            foreach ($balances[$period->employee_id] ?? [] as $balance) {
                $left += $balance->entitlementId === $period->id ? $balance->left() : 0;
            }

            return [
                'id' => $period->id,
                'name' => ($people[$period->employee_id] ?? null)->name ?? '',
                'label' => 'Periodo '.$period->anniversary_on->year,
                'days' => $left,
                'startsOn' => $period->anniversary_on->toDateString(),
                'endsOn' => ($windows ? $period->enjoy_by : $period->prescribes_on)->toDateString(),
            ];
        });
    }
}
