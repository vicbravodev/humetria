<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\SaveCenterMode;
use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Exceptions\WorkCenterNotAvailable;
use App\Modules\Attendance\Http\Requests\CenterModeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** How the people of a center mark (criterio 4.5): owner and hr change it. A center archived or of another company is a 404. */
final readonly class CenterModeController
{
    public function __invoke(CenterModeRequest $request, SaveCenterMode $save, mixed $tenant, string $workCenter): RedirectResponse
    {
        try {
            $save->handle($workCenter, $request->mode());
        } catch (WorkCenterNotAvailable) {
            abort(404);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->mode() === CenterMode::Field
            ? 'Este centro marca en campo desde la siguiente marca.'
            : 'Este centro marca igual que la empresa desde la siguiente marca.']);

        return back();
    }
}
