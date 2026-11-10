<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Actions\DecideRequest;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Http\Requests\PortalDecisionRequest;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\PortalTeamPages;
use App\Modules\Leave\Support\PortalVisitor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A manager answers, from their own portal, the requests that wait for them. The link says who they are: a request that
 * is not theirs to answer (a peer's, one given to someone else, one that went to RH with nobody above) does not exist.
 */
final class PortalTeamRequestsController extends Controller
{
    public function show(string $token, PortalVisitor $visitor, PortalTeamPages $pages): Response
    {
        $person = $visitor->open($token);

        return Inertia::render('leave/portal-team', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'requests' => $pages->requests($person->employeeId),
        ]);
    }

    public function approve(string $token, string $request, PortalVisitor $visitor, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($token, $request, true, null, $visitor, $decide);
    }

    public function reject(string $token, string $request, PortalDecisionRequest $decision, PortalVisitor $visitor, DecideRequest $decide): RedirectResponse
    {
        return $this->answer($token, $request, false, $decision->string('reason')->toString(), $visitor, $decide);
    }

    /**
     * One answer wins: a second one, or one after RH answered, is told so and changes nothing.
     */
    private function answer(string $token, string $requestId, bool $approve, ?string $reason, PortalVisitor $visitor, DecideRequest $decide): RedirectResponse
    {
        $person = $visitor->open($token);

        try {
            $decide->handle($requestId, $approve, Decider::manager($person->employeeId), $reason);
            Inertia::flash('toast', ['type' => 'success', 'message' => $approve ? 'Solicitud aprobada.' : 'Solicitud rechazada.']);
        } catch (AlreadyDecided|RequestRefused $refused) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refused->getMessage()]);
        }

        return to_route('portal.vacations.team.show', $token);
    }
}
