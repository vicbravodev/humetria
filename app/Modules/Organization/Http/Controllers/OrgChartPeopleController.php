<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The people of the org chart, for the fields that pick one of them.
 */
final class OrgChartPeopleController extends Controller
{
    public const int LIMIT = 20;

    /**
     * The active people whose name or employee number has what was typed, to choose a manager: a few at a time, by
     * name, without `except` and whoever reports to them. Name and job title only, never contact data.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(Request $request, ReportingLines $lines): array
    {
        abort_unless(Gate::any(['manage-organization', 'manage-org-chart']), 403);

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $except = $request->string('except')->toString();
        $excluded = Str::isUuid($except) ? [$except, ...$lines->subtreeIds($except)] : [];

        return array_values(Employee::query()
            ->whereNull('deactivated_at')
            ->when($excluded !== [], fn ($query) => $query->whereKeyNot($excluded))
            ->matching(['name', 'employee_number'], $search)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'employee_number', 'job_title'])
            ->map(fn (Employee $employee): array => ['value' => $employee->id, 'label' => $employee->label()])
            ->all());
    }
}
