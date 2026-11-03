<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\AdvanceRegulation;
use App\Modules\Seating\Actions\DraftRegulation;
use App\Modules\Seating\Http\Requests\AdvanceRegulationRequest;
use App\Modules\Seating\Http\Requests\RegulationPartsRequest;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\RegulationPages;
use App\Modules\Seating\Support\SeatingFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The internal regulation of a work center: RH drafts it from the center's data, edits it, and records that the comisión
 * mixta signed it, that it was deposited and that it was distributed. The Comisión of the center reads it. The system
 * registers; it deposits and sends nothing to any authority.
 */
final class RegulationController extends Controller
{
    public function show(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, RegulationPages $pages): Response
    {
        Gate::authorize('assess-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/regulation', $pages->show($tenant, $center));
    }

    public function store(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, DraftRegulation $draft): RedirectResponse
    {
        Gate::authorize('manage-seating');
        $center = $access->find($request, $workCenter);
        $draft->handle($center->id, null, $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador listo. Revísalo y edítalo con tu abogado.']);

        return redirect()->route('tenant.seating.regulation.show', [$tenant, $center->id]);
    }

    public function update(RegulationPartsRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, DraftRegulation $draft): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $draft->handle($center->id, $request->parts(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

        return redirect()->route('tenant.seating.regulation.show', [$tenant, $center->id]);
    }

    public function advance(AdvanceRegulationRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, AdvanceRegulation $advance): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $regulation = InternalRegulation::query()->where('work_center_id', $center->id)->first();
        abort_if($regulation === null, 404);

        $advance->handle($regulation, $request->to(), $request->dates(), $request->filePath(), $access->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paso registrado.']);

        return redirect()->route('tenant.seating.regulation.show', [$tenant, $center->id]);
    }

    public function file(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, SeatingFiles $files): StreamedResponse
    {
        abort_unless(Gate::allows('manage-seating') || Gate::allows('assess-seating'), 403);
        $center = $access->find($request, $workCenter);
        $regulation = InternalRegulation::query()->where('work_center_id', $center->id)->first();
        abort_if($regulation === null || $regulation->file_path === null, 404);

        $response = $files->stream($regulation->file_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
