<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Actions\SavePulseSchedule;
use App\Modules\Engagement\Http\Requests\PulseScheduleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Saves when the pulse of the company goes out by itself. Owner and hr only. */
final readonly class PulseScheduleController
{
    public function update(PulseScheduleRequest $request, mixed $tenant, SavePulseSchedule $save): RedirectResponse
    {
        $save->handle(
            $request->frequency(),
            $request->integer('day_of_month'),
            $request->integer('open_days'),
            $request->boolean('active'),
            $request->nextMode(),
            $request->user()->id ?? abort(403),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La programación del pulso se guardó.']);

        return back();
    }
}
