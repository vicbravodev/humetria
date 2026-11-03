<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\Acknowledge;
use App\Modules\Disclosure\Http\Requests\AcknowledgeRequest;
use App\Modules\Disclosure\Support\PortalPolicyPages;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The policy page of a person's portal, under the same link as the rest (criterio C-53): read the policy in force in their
 * center and confirm they read it, once. A link that is not valid is a 404 that says nothing about whether it ever existed.
 */
final class PortalPolicyController extends Controller
{
    public function show(string $token, PortalAccess $access, PortalPolicyPages $pages): Response
    {
        return Inertia::render('disclosure/portal-policy', $pages->show($this->person($token, $access), $token));
    }

    public function acknowledge(string $token, AcknowledgeRequest $request, PortalAccess $access, Acknowledge $acknowledge): RedirectResponse
    {
        $acknowledge->handle($this->person($token, $access)->employeeId, $request->documentId());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Quedó registrado que leyó la política.']);

        return to_route('portal.policy.show', $token);
    }

    private function person(string $token, PortalAccess $access): PortalIdentity
    {
        return $access->open($token) ?? abort(404);
    }
}
