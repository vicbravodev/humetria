<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Actions\SetManager;
use App\Modules\Organization\Enums\ReportingIssue;
use App\Modules\Organization\Http\Requests\ManagerRequest;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\ReportingGaps;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Calidad de datos»: what a load usually leaves wrong in the reporting line (nobody above, several at the top, a
 * manager who left), counted, with the people of each a page at a time and their manager fixed in place. Name, job
 * title, area and work center only: never contact data.
 */
final class OrgChartQualityController extends Controller
{
    public function index(Request $request, ReportingGaps $gaps, WorkCenters $workCenters): Response|RedirectResponse
    {
        Gate::authorize('view-org-chart');

        $counts = $gaps->counts();
        $issue = ReportingIssue::tryFrom($request->string('issue')->toString());

        // The address always names the list: fixing someone comes back to the same one, not to whichever is first then.
        if ($issue === null) {
            $first = array_find(ReportingIssue::cases(), fn (ReportingIssue $issue): bool => $issue->pending($counts[$issue->value])) ?? ReportingIssue::WithoutManager;

            return redirect()->to($request->fullUrlWithQuery(['issue' => $first->value]));
        }

        $centers = array_column($workCenters->all(), 'name', 'id');

        return Inertia::render('organization/org-chart-quality', [
            'issues' => array_map(fn (ReportingIssue $issue): array => [
                'value' => $issue->value,
                'label' => $issue->label(),
                'hint' => $issue->hint($counts[$issue->value]),
                'count' => $counts[$issue->value],
                'pending' => $issue->pending($counts[$issue->value]),
            ], ReportingIssue::cases()),
            'issue' => $issue->value,
            'people' => $gaps->page($issue)->through(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name ?? $employee->employee_number ?? '',
                'job_title' => $employee->job_title ?? '',
                'area' => $employee->area->name ?? '',
                'work_center' => $centers[$employee->work_center_id ?? ''] ?? '',
                'manager' => $employee->manager === null ? null : ['value' => $employee->manager->id, 'label' => $employee->manager->label()],
            ]),
            'can_manage' => Gate::allows('manage-org-chart'),
        ]);
    }

    /**
     * Another company's person is not found: the same 404 as one that does not exist. Someone who left is a 409:
     * their place in the line is not touched.
     */
    public function update(ManagerRequest $request, mixed $tenant, string $employee, SetManager $setManager): RedirectResponse
    {
        $person = Employee::query()->findOrFail($employee);
        abort_if($person->deactivated_at !== null, 409);

        $setManager->handle($person, $request->managerId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jefe actualizado.']);

        return back();
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.
}
