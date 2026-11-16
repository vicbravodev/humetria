<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\Acknowledge;
use App\Modules\Disclosure\Http\Requests\AcknowledgeRequest;
use App\Modules\Disclosure\Support\PortalPolicyPages;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The policy page of the person of the session, in their pages (criterio C-53): read the policy in force in their center and
 * confirm they read it, once.
 */
final class PortalPolicyController extends Controller
{
    public function show(string $tenant, WorkerSession $session, PortalPolicyPages $pages): Response
    {
        return Inertia::render('disclosure/portal-policy', $pages->show($session->current() ?? abort(404), $tenant));
    }

    public function acknowledge(string $tenant, AcknowledgeRequest $request, WorkerSession $session, Acknowledge $acknowledge): RedirectResponse
    {
        $acknowledge->handle(($session->current() ?? abort(404))->employeeId, $request->documentId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Quedó registrado que leyó la política.']);

        return to_route('portal.policy.show', $tenant);
    }
}
