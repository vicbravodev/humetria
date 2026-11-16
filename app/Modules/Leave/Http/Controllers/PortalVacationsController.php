<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Actions\CancelRequest;
use App\Modules\Leave\Actions\RequestLeave;
use App\Modules\Leave\Exceptions\AlreadyDecided;
use App\Modules\Leave\Exceptions\RequestRefused;
use App\Modules\Leave\Http\Requests\PortalLeaveRequest;
use App\Modules\Leave\Support\Decider;
use App\Modules\Leave\Support\PortalVacationsPages;
use App\Modules\Leave\Support\PortalVisitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis vacaciones» in the person's pages: their days by period and their requests. The session says who they are, and
 * only ever shows, and acts on, what is of that person.
 */
final class PortalVacationsController extends Controller
{
    public function show(string $tenant, PortalVisitor $visitor): Response
    {
        $person = $visitor->current();

        return Inertia::render('leave/portal', [
            'slug' => $tenant,
            'company' => $person->companyName,
            'name' => $person->personName,
            ...PortalVacationsPages::for($person->employeeId),
        ]);
    }

    /**
     * What the dates would be as a request, before sending it: nothing is written.
     */
    public function preview(string $tenant, PortalLeaveRequest $request, PortalVisitor $visitor, RequestLeave $ask): JsonResponse
    {
        $person = $visitor->current();

        try {
            $plan = $ask->preview($person->employeeId, $request->typeId(), $request->startsOn(), $request->endsOn());
        } catch (RequestRefused $refused) {
            throw PortalLeaveRequest::refusal($refused);
        }

        return response()->json(['workingDays' => $plan['workingDays'], 'hadBalance' => $plan['hadBalance'], 'shortBy' => $plan['shortBy'], 'continuousNotice' => $plan['continuousNotice']]);
    }

    public function store(string $tenant, PortalLeaveRequest $request, PortalVisitor $visitor, RequestLeave $ask): RedirectResponse
    {
        $person = $visitor->current();

        try {
            $ask->handle($person->employeeId, $request->typeId(), $request->startsOn(), $request->endsOn(), $request->string('note')->toString());
        } catch (RequestRefused $refused) {
            throw PortalLeaveRequest::refusal($refused);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enviamos su solicitud. Le avisaremos cuando la respondan.']);

        return to_route('portal.vacations.show', $tenant);
    }

    /**
     * The person cancels a request of theirs; one that is not theirs does not exist.
     */
    public function cancel(string $tenant, string $request, PortalVisitor $visitor, CancelRequest $cancel): RedirectResponse
    {
        $person = $visitor->current();

        try {
            $cancel->handle($request, Decider::requester($person->employeeId));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Su solicitud quedó cancelada.']);
        } catch (AlreadyDecided) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta solicitud ya no se puede cancelar.']);
        }

        return to_route('portal.vacations.show', $tenant);
    }
}
