<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\ExportOrgChart;
use App\Modules\Organization\Actions\SetManager;
use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Http\Requests\ManagerRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgIndicators;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Lista» of the org chart: everybody, or only what a load usually leaves wrong in the reporting line (nobody above, a
 * manager with more than 15 people, a manager who left), each view counted, with its people a page at a time, searched
 * on the server and their manager fixed in place. Name, job title, area, work center and counts only: never contact
 * data.
 */
final class OrgChartQualityController extends Controller
{
    public function index(Request $request, ReportingGaps $gaps, WorkCenters $workCenters, OrgIndicators $indicators): Response|RedirectResponse
    {
        Gate::authorize('view-org-chart');

        $counts = $gaps->counts();
        $views = ReportingIssue::views();
        $issue = ReportingIssue::tryFrom($request->string('issue')->toString());

        // The address always names the view: fixing someone comes back to the same one, not to whichever is first then.
        if ($issue === null || ! in_array($issue, $views, true)) {
            $first = array_find($views, fn (ReportingIssue $view): bool => $view->pending($counts[$view->value])) ?? ReportingIssue::All;

            return redirect()->to($request->fullUrlWithQuery(['issue' => $first->value]));
        }

        $centers = array_column($workCenters->all(), 'name', 'id');
        $center = Str::isUuid($request->string('work_center')->toString()) ? $workCenters->profile($request->string('work_center')->toString()) : null;
        $search = $request->string('q')->trim()->limit(100, '')->toString();

        return Inertia::render('organization/org-chart-quality', [
            'issues' => array_map(fn (ReportingIssue $view): array => [
                'value' => $view->value,
                'label' => $view->label(),
                'count' => $counts[$view->value],
                'pending' => $view->pending($counts[$view->value]),
            ], $views),
            'issue' => $issue->value,
            'filters' => ['q' => $search, 'work_center' => $center === null ? null : ['value' => $center->id, 'label' => $center->name]],
            // Of the whole company: the search and the center narrow the list, never the facts above it.
            'total_people' => $counts['all'],
            'with_manager' => $counts['all'] - $counts['no_manager'],
            'levels' => $indicators->handle()['levels'],
            'people' => $gaps->page($issue, search: $search, workCenterId: $center?->id)->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name ?? $employee->employee_number ?? '',
                'job_title' => $employee->job_title ?? '',
                'area' => $employee->area->name ?? '',
                'work_center' => $centers[$employee->work_center_id ?? ''] ?? '',
                'manager' => $employee->manager === null ? null : ['value' => $employee->manager->id, 'label' => $employee->manager->label()],
                'manager_inactive' => $employee->manager?->deactivated_at !== null,
                'direct_reports' => is_int($employee->getAttributeValue('direct_reports')) ? $employee->getAttributeValue('direct_reports') : 0,
            ]),
            'can_manage' => Gate::allows('manage-org-chart'),
        ]);
    }

    /**
     * The list as a CSV: every active person with their manager and the size of their team. For whoever may see the chart.
     *
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     */
    public function export(mixed $tenant, ExportOrgChart $export): StreamedResponse
    {
        Gate::authorize('view-org-chart');

        $file = $export->handle();

        return response()->streamDownload(function () use ($file): void {
            echo $file['content'];
        }, 'organigrama.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Another company's person is not found: the same 404 as one that does not exist. Someone who left is a 409:
     * their place in the line is not touched.
     */
    public function update(ManagerRequest $request, mixed $tenant, string $employee, SetManager $setManager): RedirectResponse|JsonResponse
    {
        $person = Employee::query()->findOrFail($employee);
        abort_if($person->deactivated_at !== null, 409);

        $setManager->handle($person, $request->managerId());

        // The chart moves people with its own request and tells the person itself (with «Deshacer»): no flash.
        if ($request->expectsJson()) {
            return response()->json(['manager_id' => $request->managerId()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jefe actualizado.']);

        return back();
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
}
