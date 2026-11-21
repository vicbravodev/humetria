<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\ResolveDispute;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Http\Requests\ResolveDisputeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Owner and hr resolve a person's dispute of their week, once, from RH's overview. A week of another company is a 404. */
final readonly class WeekResolutionController
{
    public function __invoke(ResolveDisputeRequest $request, mixed $tenant, string $week, ResolveDispute $resolve): RedirectResponse
    {
        try {
            $resolve->handle($week, ($request->user() ?? abort(401))->id, $request->note());
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Objeción resuelta. La semana vuelve a esperar la respuesta de la persona.']);
        } catch (WeekRejected) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta objeción ya se resolvió.']);
        }

        return back();
    }
}
