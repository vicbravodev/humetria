<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Actions\CreatePulseDraft;
use App\Modules\Engagement\Http\Requests\PulseDraftRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Starts a pulse draft in the mode RH chose. Owner and hr only. */
final readonly class PulseDraftController
{
    public function store(PulseDraftRequest $request, mixed $tenant, CreatePulseDraft $create): RedirectResponse
    {
        try {
            // A transaction of its own: the refusal of the database leaves nothing half done, nor a poisoned outer transaction.
            $survey = DB::transaction(fn () => $create->handle($request->mode(), $request->user()->id ?? abort(403)));
        } catch (UniqueConstraintViolationException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Ya hay un pulso en preparación o en curso.']);

            return to_route('tenant.pulse.index', $tenant);
        }

        return to_route('tenant.pulse.show', [$tenant, $survey]);
    }
}
