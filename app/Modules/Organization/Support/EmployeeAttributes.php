<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\JobGrade;
use Carbon\CarbonImmutable;

/**
 * The columns an employee's data fills in, shared by adding, correcting and importing.
 */
final class EmployeeAttributes
{
    /**
     * The person and where they work.
     *
     * @param  list<string>  $absent  the columns an import file of a union does not have (name, area_id, job_title), which an update leaves as they are.
     * @return array{work_center_id?: string|null, area_id?: string|null, name?: string|null, email: string|null, mobile: string|null, gender: Gender|null, job_title?: string|null, employee_number: string|null}
     */
    public static function from(EmployeeData $data, ?Area $area, array $absent = []): array
    {
        return [
            'work_center_id' => $data->workCenterId,
            ...(in_array('area_id', $absent, true) ? [] : ['area_id' => $area?->id]),
            ...(in_array('name', $absent, true) ? [] : ['name' => $data->name]),
            'email' => $data->email,
            'mobile' => $data->mobile,
            'gender' => $data->gender,
            ...(in_array('job_title', $absent, true) ? [] : ['job_title' => $data->jobTitle]),
            'employee_number' => $data->employeeNumber,
        ];
    }

    /**
     * What the pay scale uses, apart because an import file may not carry it and then leaves it as it is.
     *
     * @param  list<string>  $absent  the columns to leave out, which an update then leaves as they are.
     * @return array{job_grade_id?: string|null, hired_on?: CarbonImmutable|null, seniority_on?: CarbonImmutable|null, staff_group?: StaffGroup|null, state?: string|null}
     */
    public static function payScale(EmployeeData $data, ?JobGrade $jobGrade, array $absent = []): array
    {
        return [
            ...(in_array('job_grade_id', $absent, true) ? [] : ['job_grade_id' => $jobGrade?->id]),
            ...(in_array('hired_on', $absent, true) ? [] : ['hired_on' => $data->hiredOn]),
            ...(in_array('seniority_on', $absent, true) ? [] : ['seniority_on' => $data->seniorityOn]),
            ...(in_array('staff_group', $absent, true) ? [] : ['staff_group' => $data->staffGroup]),
            ...(in_array('state', $absent, true) ? [] : ['state' => $data->state]),
        ];
    }
}
