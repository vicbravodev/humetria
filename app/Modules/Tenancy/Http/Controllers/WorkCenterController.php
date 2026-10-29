<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
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
use Inertia\Inertia;
use Inertia\Response;

/**
 * The work centers of the company in the URL. ResolveTenant has already let only its members in.
 */
final class WorkCenterController extends Controller
{
    public function index(Request $request, ListWorkCenters $listWorkCenters): Response
    {
        $workCenters = $listWorkCenters->handle();
        $active = $workCenters->whereNull('archived_at');
        $archived = $workCenters->whereNotNull('archived_at');
        $showArchived = $request->boolean('archived');

        return Inertia::render('tenancy/work-centers', [
            'view' => $showArchived ? 'archived' : 'active',
            'counts' => ['active' => $active->count(), 'archived' => $archived->count()],
            'workCenters' => ($showArchived ? $archived : $active)->values()->map($this->row(...))->all(),
            'states' => collect(MexicanState::cases())
                ->map(fn (MexicanState $state): array => ['value' => $state->value, 'label' => $state->label()])
                ->sortBy('label')
                ->values()
                ->all(),
        ]);
    }

    public function store(WorkCenterRequest $request, Tenant $tenant, CreateWorkCenter $createWorkCenter): RedirectResponse
    {
        $createWorkCenter->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo dado de alta.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function update(WorkCenterRequest $request, Tenant $tenant, string $workCenter, UpdateWorkCenter $updateWorkCenter): RedirectResponse
    {
        $updateWorkCenter->handle($this->find($workCenter, archived: false), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo actualizado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function archive(Tenant $tenant, string $workCenter, ArchiveWorkCenter $archiveWorkCenter): RedirectResponse
    {
        $archiveWorkCenter->handle($this->find($workCenter, archived: false));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo archivado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
    }

    public function restore(Tenant $tenant, string $workCenter, RestoreWorkCenter $restoreWorkCenter): RedirectResponse
    {
        $restoreWorkCenter->handle($this->find($workCenter, archived: true));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de trabajo restaurado.']);

        return redirect()->route('tenant.work-centers.index', $tenant);
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
     * @return array<string, string|null>
     */
    private function row(WorkCenter $workCenter): array
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
        ];
    }
}
