<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Data\EmployeeData;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Models\Area;

/**
 * The columns an employee's data fills in, shared by adding, correcting and importing.
 */
final class EmployeeAttributes
{
    /**
     * @return array{work_center_id: string, area_id: string, name: string, email: string|null, mobile: string|null, gender: Gender, job_title: string, employee_number: string|null}
     */
    public static function from(EmployeeData $data, Area $area): array
    {
        return [
            'work_center_id' => $data->workCenterId,
            'area_id' => $area->id,
            'name' => $data->name,
            'email' => $data->email,
            'mobile' => $data->mobile,
            'gender' => $data->gender,
            'job_title' => $data->jobTitle,
            'employee_number' => $data->employeeNumber,
        ];
    }
}
