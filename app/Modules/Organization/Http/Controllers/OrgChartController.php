<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Data\OrgFilters;
use App\Modules\Organization\Data\OrgNode;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgTree;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Organigrama»: who reports to whom, a level at a time. Name, job title, area, work center and counts only: never
 * contact data.
 *
 * @phpstan-import-type OrgNodeShape from OrgNode
 */
final class OrgChartController extends Controller
{
    public function index(Request $request, OrgTree $tree, WorkCenters $workCenters): Response
    {
        Gate::authorize('view-org-chart');

        $filters = $this->filters($request);
        $focus = $request->query('focus');
        // The chain ends in the focused person: nobody, or someone who left, is not found (as in another company).
        $chain = is_string($focus) ? $tree->chain($focus) : [];
        abort_if($focus !== null && array_last($chain)?->active !== true, 404);
        $counts = Employee::query()->whereNull('deactivated_at')->toBase()->selectRaw('count(*) AS total, count(manager_id) AS with_manager')->first();

        return Inertia::render('organization/org-chart', [
            'roots' => $this->nodes($tree->roots($filters)),
            'chain' => array_map(fn (OrgNode $node): array => $node->toArray(), $chain),
            'focus' => is_string($focus) ? ['id' => $focus, 'children' => $this->nodes($tree->children($focus, $filters))] : null,
            // Each chosen filter with its name, for the fields that search them on the server.
            'filters' => [
                'work_center' => $filters->workCenterId === null ? null : ['value' => $filters->workCenterId, 'label' => $workCenters->profile($filters->workCenterId)->name ?? ''],
                'area' => $filters->areaId === null ? null : ['value' => $filters->areaId, 'label' => Area::query()->find($filters->areaId, ['id', 'name'])->name ?? ''],
            ],
            // Of the whole company: the filters narrow the tree, never the sentence above it.
            'total_people' => $counts->total ?? 0,
            'with_manager' => $counts->with_manager ?? 0,
            'can_manage' => Gate::allows('manage-org-chart'),
        ]);
    }

    /**
     * The active direct reports of a person, a page at a time, for the tree to open them. Another company's person
     * is not found: the same 404 as one that does not exist.
     *
     * @return LengthAwarePaginator<int, OrgNodeShape>
     */
    public function reports(Request $request, mixed $tenant, string $employee, OrgTree $tree): LengthAwarePaginator
    {
        Gate::authorize('view-org-chart');

        return $this->nodes($tree->children(Employee::query()->findOrFail($employee, ['id'])->id, $this->filters($request)));
    }

    // The $tenant parameter receives the company Tenancy resolved from the URL; it is only there to keep the route
    // parameters in order.

    private function filters(Request $request): OrgFilters
    {
        $uuid = fn (string $key): ?string => Str::isUuid($request->string($key)->toString()) ? $request->string($key)->toString() : null;

        return new OrgFilters($uuid('work_center'), $uuid('area'));
    }

    /**
     * @param  LengthAwarePaginator<int, OrgNode>  $nodes
     * @return LengthAwarePaginator<int, OrgNodeShape>
     */
    private function nodes(LengthAwarePaginator $nodes): LengthAwarePaginator
    {
        return $nodes->through(fn (OrgNode $node): array => $node->toArray())->withQueryString();
    }
}
