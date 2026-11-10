<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What «Mis vacaciones» shows a person in their portal: only what is theirs. Days and dates, never an amount; the note
 * they wrote is not sent back.
 */
final class PortalVacationsPages
{
    public const int PER_PAGE = 10;

    /**
     * @return array{today: string, periods: list<array{year: int, granted: int, available: int, enjoyBy: string, mayPrescribe: bool}>, advanced: int, teamPending: int, requests: LengthAwarePaginator<int, array{id: string, type: string|null, startsOn: string, endsOn: string, workingDays: int, status: string, statusLabel: string, hadBalance: bool|null, rejectionReason: string|null, canCancel: bool}>, types: list<array{id: string, name: string}>}
     */
    public static function for(string $employeeId): array
    {
        return [
            'today' => PeriodWindow::today()->toDateString(),
            'periods' => self::periods($employeeId),
            'advanced' => max(0, -(Balances::advanced([$employeeId])[$employeeId] ?? 0)),
            'requests' => self::requests($employeeId),
            'teamPending' => PortalTeamPages::pendingFor($employeeId),
            'types' => array_values(LeaveType::query()->whereNull('archived_at')->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->name])->all()),
        ];
    }

    /**
     * The periods that still have days, from the oldest. One that is past its eighteen months is marked, never taken off
     * (criterio C-11).
     *
     * @return list<array{year: int, granted: int, available: int, enjoyBy: string, mayPrescribe: bool}>
     */
    private static function periods(string $employeeId): array
    {
        $today = PeriodWindow::today()->toDateString();
        $periods = array_values(array_filter(Balances::of([$employeeId])[$employeeId] ?? [], fn (PeriodBalance $period): bool => $period->left() > 0));
        $granted = LeaveEntitlement::query()->whereIn('id', array_map(fn (PeriodBalance $period): string => $period->entitlementId, $periods))->get(['id', 'statutory_days', 'extra_days'])->keyBy('id');

        return array_map(fn (PeriodBalance $period): array => [
            'year' => $period->anniversaryOn->year,
            'granted' => ($granted[$period->entitlementId]->statutory_days ?? 0) + ($granted[$period->entitlementId]->extra_days ?? 0),
            'available' => $period->left(),
            'enjoyBy' => $period->enjoyBy->toDateString(),
            'mayPrescribe' => $today > $period->prescribesOn->toDateString(),
        ], $periods);
    }

    /**
     * Their requests, the newest dates first. The type is theirs to read: they chose it.
     *
     * @return LengthAwarePaginator<int, array{id: string, type: string|null, startsOn: string, endsOn: string, workingDays: int, status: string, statusLabel: string, hadBalance: bool|null, rejectionReason: string|null, canCancel: bool}>
     */
    private static function requests(string $employeeId): LengthAwarePaginator
    {
        $page = LeaveRequest::query()->where('employee_id', $employeeId)->orderByDesc('starts_on')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
        $types = LeaveType::query()->whereIn('id', $page->getCollection()->pluck('leave_type_id')->filter()->unique()->values())->get(['id', 'name'])->mapWithKeys(fn (LeaveType $type): array => [$type->id => $type->name]);

        return $page->through(fn (LeaveRequest $request): array => [
            'id' => $request->id,
            'type' => $request->leave_type_id === null ? null : $types->get($request->leave_type_id, 'Permiso'),
            'startsOn' => $request->starts_on->toDateString(),
            'endsOn' => $request->ends_on->toDateString(),
            'workingDays' => $request->working_days,
            'status' => $request->status->value,
            'statusLabel' => $request->status->label(),
            'hadBalance' => $request->had_balance,
            'rejectionReason' => $request->rejection_reason,
            'canCancel' => CancelRequest::openToRequester($request),
        ]);
    }
}
