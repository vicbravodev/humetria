<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Contracts\Absences;
use App\Modules\Leave\Data\AbsenceData;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The absences of the current company: its requests approved or already enjoyed, by their dates.
 */
final class CompanyAbsences implements Absences
{
    public function absentOn(CarbonImmutable $day, array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        // A fresh get() is already a list.
        return array_values($this->touching($day, $day, $employeeIds)->distinct()->get(['employee_id'])->map(fn (LeaveRequest $request): string => $request->employee_id)->all()); // @pest-mutate-ignore: UnwrapArrayValues
    }

    public function between(CarbonImmutable $from, CarbonImmutable $to, array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return array_values($this->touching($from, $to, $employeeIds)->orderBy('starts_on')->orderBy('id')->get(['id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on'])
            ->map(fn (LeaveRequest $request): AbsenceData => new AbsenceData($request->employee_id, $request->starts_on, $request->ends_on, $request->leave_type_id === null ? 'vacation' : 'leave'))
            ->all()); // @pest-mutate-ignore: UnwrapArrayValues
    }

    /**
     * @param  list<string>  $employeeIds
     * @return Builder<LeaveRequest>
     */
    private function touching(CarbonImmutable $from, CarbonImmutable $to, array $employeeIds): Builder
    {
        return LeaveRequest::query()
            ->among('employee_id', $employeeIds)
            ->whereIn('status', [RequestStatus::Approved, RequestStatus::Taken])
            ->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString());
    }
}
