<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\SaveZone;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Http\Requests\ZoneRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The zones of a center, where its people may mark (criterio 3.4): owner and hr add and change them. None is deleted: the
 * marks taken in a zone keep pointing at it. A center archived or of another company, or a zone of another center, is a 404.
 */
final readonly class ZoneController
{
    public function __construct(private SaveZone $save) {}

    public function store(ZoneRequest $request, mixed $tenant, string $workCenter): RedirectResponse
    {
        return $this->save($request, $workCenter, null);
    }

    public function update(ZoneRequest $request, mixed $tenant, string $workCenter, string $zone): RedirectResponse
    {
        return $this->save($request, $workCenter, $zone);
    }

    private function save(ZoneRequest $request, string $workCenter, ?string $zone): RedirectResponse
    {
        try {
            $this->save->handle($workCenter, $request->name(), $request->latitude(), $request->longitude(), $request->radius(), $zone);
        } catch (WorkCenterNotAvailable) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zona guardada.']);

        return back();
    }
}
