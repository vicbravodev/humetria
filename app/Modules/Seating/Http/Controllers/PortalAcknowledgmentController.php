<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\AcknowledgeRisks;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The person confirms, from their pages, that they were informed of the risks and measures of their assessment. A person
 * with nothing signed to confirm is a 404 that says nothing more.
 */
final class PortalAcknowledgmentController extends Controller
{
    public function store(string $tenant, WorkerSession $session, AcknowledgeRisks $acknowledge): RedirectResponse
    {
        $person = $session->current() ?? abort(404);

        $acknowledge->handle($person);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Quedó registrado que le informaron.']);

        return to_route('portal.seating.show', $tenant);
    }
}
