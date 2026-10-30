<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Tenancy\Actions\ArchiveWorkCenter;
use App\Modules\Tenancy\Actions\CreateWorkCenter;
use App\Modules\Tenancy\Actions\ListWorkCenters;
use App\Modules\Tenancy\Actions\RestoreWorkCenter;
use App\Modules\Tenancy\Actions\UpdateWorkCenter;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Http\Requests\WorkCenterRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\DisplayDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The work centers of the company in the URL. ResolveTenant has already let only its members in.
 */
final class WorkCenterController extends Controller
{
    public function __construct(private readonly Headcount $headcount, private readonly Nom035Guides $guides) {}

    public function index(Request $request, ListWorkCenters $listWorkCenters): Response
    {
        $headcount = $this->headcount->byWorkCenter();

        $showArchived = $request->boolean('archived');
        $archived = WorkCenter::query()->whereNotNull('archived_at')->count();

        return Inertia::render('tenancy/work-centers', [
            'view' => $showArchived ? 'archived' : 'active',
            'counts' => ['active' => WorkCenter::query()->count() - $archived, 'archived' => $archived],
            'workCenters' => $listWorkCenters->handle($showArchived)
                ->through(fn (WorkCenter $workCenter): array => $this->row($workCenter, $headcount[$workCenter->id] ?? 0)),
            'states' => collect(MexicanState::cases())
                ->map(fn (MexicanState $state): array => ['value' => $state->value, 'label' => $state->label()])
                ->sortBy('label')
                ->values()
                ->all(),
        ]);
    }

    public function store(WorkCenterRequest $request, Tenant $tenant, CreateWorkCenter $createWorkCenter): RedirectResponse
    {
        $this->authorizeChanges();

        $createWorkCenter->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo dado de alta.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function update(WorkCenterRequest $request, Tenant $tenant, string $workCenter, UpdateWorkCenter $updateWorkCenter): RedirectResponse
    {
        $this->authorizeChanges();

        $updateWorkCenter->handle($this->find($workCenter, archived: false), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo actualizado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function archive(Tenant $tenant, string $workCenter, ArchiveWorkCenter $archiveWorkCenter): RedirectResponse
    {
        $this->authorizeChanges();

        $archiveWorkCenter->handle($this->find($workCenter, archived: false));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo archivado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function restore(Tenant $tenant, string $workCenter, RestoreWorkCenter $restoreWorkCenter): RedirectResponse
    {
        $this->authorizeChanges();

        $restoreWorkCenter->handle($this->find($workCenter, archived: true));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo restaurado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    /** Every role sees the work centers; only those who manage the organization change them. */
    private function authorizeChanges(): void
    {
        Gate::authorize('manage-organization');
    }

    /**
     * Looked up here, not by route model binding: binding runs before ResolveTenant, when the company scope cannot
     * be applied yet. Another company's work center is not found, the same 404 as one that does not exist.
     * One that is not in the expected state is a 409: archived ones are not edited nor archived again.
     */
    private function find(string $id, bool $archived): WorkCenter
    {
        $workCenter = WorkCenter::query()->findOrFail($id);

        abort_if(($workCenter->archived_at !== null) !== $archived, 409);

        return $workCenter;
    }

    /**
     * @return array<string, string|int|list<string>|null>
     */
    private function row(WorkCenter $workCenter, int $employees): array
    {
        return [
            'id' => $workCenter->id,
            'name' => $workCenter->name,
            'street' => $workCenter->street,
            'exterior_number' => $workCenter->exterior_number,
            'interior_number' => $workCenter->interior_number,
            'neighborhood' => $workCenter->neighborhood,
            'postal_code' => $workCenter->postal_code,
            'municipality' => $workCenter->municipality,
            'state' => $workCenter->state->value,
            'main_activity' => $workCenter->main_activity,
            'address' => $workCenter->address(),
            'archived_on' => $workCenter->archived_at === null ? null : DisplayDate::day($workCenter->archived_at),
            'employees' => $employees,
            'guides' => $this->guides->for($employees),
        ];
    }
}
