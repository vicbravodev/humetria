<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\SaveSeat;
use App\Modules\Seating\Http\Requests\SeatRequest;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CenterAccess;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Seating\Support\SeatPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The seat inventory of a work center. A seat is registered and edited, never deleted (criterio C-17). Photos leave only
 * through an authorized route: to RH and the Comisión of the center, never to the viewer, never cached.
 */
final class SeatController extends Controller
{
    public function index(Request $request, mixed $tenant, string $workCenter, CenterAccess $access, SeatPages $pages): Response
    {
        Gate::authorize('view-seating');
        $center = $access->find($request, $workCenter);

        return Inertia::render('seating/seats', $pages->index($tenant, $center, $request->string('edit')->toString() ?: null));
    }

    public function store(SeatRequest $request, mixed $tenant, string $workCenter, CenterAccess $access, SaveSeat $save): RedirectResponse
    {
        $center = $access->find($request, $workCenter);
        $save->handle($request->toData(null, $center->id), $request->photoFile());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asiento registrado.']);

        return redirect()->route('tenant.seating.seats.index', [$tenant, $center->id]);
    }

    public function update(SeatRequest $request, mixed $tenant, string $seat, CenterAccess $access, SaveSeat $save): RedirectResponse
    {
        $found = Seat::query()->find($seat);
        abort_if($found === null, 404);
        $center = $access->find($request, $found->work_center_id);
        $save->handle($request->toData($found->id, $center->id), $request->photoFile());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Asiento actualizado.']);

        return redirect()->route('tenant.seating.seats.index', [$tenant, $center->id]);
    }

    public function photo(Request $request, mixed $tenant, string $seat, CenterAccess $access, SeatingFiles $files): StreamedResponse
    {
        abort_unless(Gate::allows('manage-seating') || Gate::allows('assess-seating'), 403);
        $found = Seat::query()->find($seat);
        abort_if($found === null || $found->photo_path === null, 404);
        $access->find($request, $found->work_center_id);

        $response = $files->stream($found->photo_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * The positions of the center that match what was typed, for the field that picks one.
     *
     * @return list<array{value: string, label: string}>
     */
    public function positions(Request $request, mixed $tenant, string $workCenter, CenterAccess $access): array
    {
        Gate::authorize('manage-seating');
        $center = $access->find($request, $workCenter);

        return array_values(StandingPosition::query()->where('work_center_id', $center->id)->matching(['name'], $request->string('q')->trim()->limit(100, '')->toString())->orderBy('name')->limit(20)->get(['id', 'name'])
            ->map(fn (StandingPosition $position): array => ['value' => $position->id, 'label' => $position->name])->all());
    }
}
