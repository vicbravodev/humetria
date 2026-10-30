<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Modules\Organization\Models\Employee;

final class CountEmployees
{
    /**
     * The current company's people at a glance, in one query: active and deactivated, the active ones without email
     * (the survey links go out by email) and how many work centers the active ones work in.
     *
     * @return array{active: int, inactive: int, without_email: int, work_centers: int}
     */
    public function handle(): array
    {
        $row = Employee::query()->toBase()->selectRaw(
            'count(*) filter (where deactivated_at is null)::int as active, '
            .'count(*) filter (where deactivated_at is not null)::int as inactive, '
            .'count(*) filter (where deactivated_at is null and email is null)::int as without_email, '
            .'count(distinct work_center_id) filter (where deactivated_at is null)::int as work_centers',
        )->first();
        $count = fn (string $name): int => is_int($row?->{$name}) ? $row->{$name} : 0;

        return [
            'active' => $count('active'),
            'inactive' => $count('inactive'),
            'without_email' => $count('without_email'),
            'work_centers' => $count('work_centers'),
        ];
    }
}
