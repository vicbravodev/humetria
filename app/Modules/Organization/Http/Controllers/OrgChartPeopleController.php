<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\ReportingLines;
use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * name, without `except` and whoever reports to them (who cannot be their manager). Name and job title only, never contact data.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(Request $request, ReportingLines $lines): array
    {
        abort_unless(Gate::any(['manage-organization', 'manage-org-chart']), 403);

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $except = $request->string('except')->toString();
        // `keep_reports` leaves out the person alone: whoever reports to them may take their place when they leave.
        $excluded = Str::isUuid($except) ? [$except, ...($request->boolean('keep_reports') ? [] : $lines->subtreeIds($except))] : [];

        return $this->people($search, $excluded);
    }

    /**
     * The active people whose name or employee number has what was typed, to focus one in the org chart.
     *
     * @return list<array{value: string, label: string}>
     */
    public function search(Request $request): array
    {
        Gate::authorize('view-org-chart');

        return $this->people($request->string('q')->trim()->limit(100, '')->toString(), []);
    }

    /**
     * The active people with no manager who lead nobody, the ones the chart does not draw yet, a page at a time and
     * by name, for whoever edits the structure to hang them from their manager. Name, job title, area and work
     * center only: never contact data.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, job_title: string, area: string, work_center: string}>
     */
    public function unassigned(Request $request, ReportingGaps $gaps, WorkCenters $workCenters): LengthAwarePaginator
    {
        Gate::authorize('manage-org-chart');

        $centers = array_column($workCenters->all(), 'name', 'id');
        $search = $request->string('q')->trim()->limit(100, '')->toString();

        return $gaps->page(ReportingIssue::WithoutManager, search: $search)->through(fn (Employee $employee): array => [
            'id' => $employee->id,
            'name' => $employee->name ?? $employee->employee_number ?? '',
            'job_title' => $employee->job_title ?? '',
            'area' => $employee->area->name ?? '',
            'work_center' => $centers[$employee->work_center_id ?? ''] ?? '',
        ]);
    }

    /**
     * @param  list<string>  $excluded
     * @return list<array{value: string, label: string}>
     */
    private function people(string $search, array $excluded): array
    {
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
