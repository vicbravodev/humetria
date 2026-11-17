<?php

declare(strict_types=1);

namespace App\Modules\Seating\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Seating\Actions\AcknowledgeRisks;
use App\Modules\Seating\Support\PortalAssessment;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The result of the person's assessment on a page of its own (finding 34), and their confirmation that they were informed of
 * its risks and measures. Both take a recent proof. A person with nothing signed is a 404 that says nothing more.
 */
final class PortalAcknowledgmentController extends Controller
{
    public function show(string $tenant, WorkerSession $session): Response
    {
        $person = $session->current() ?? abort(404);
        $assessment = new PortalAssessment($person->employeeId);
        $toConfirm = $assessment->toConfirm();
        $acknowledgedOn = $assessment->acknowledgedOn();
        abort_if($toConfirm === null && $acknowledgedOn === null, 404);

        return Inertia::render('seating/portal-acknowledgment', [
            'slug' => $tenant,
            'company' => $person->companyName,
            'acknowledgment' => $toConfirm,
            'acknowledgedOn' => $acknowledgedOn,
        ]);
    }

    public function store(string $tenant, WorkerSession $session, AcknowledgeRisks $acknowledge): RedirectResponse
    {
        $person = $session->current() ?? abort(404);

        $acknowledge->handle($person);

        return to_route('portal.seating.acknowledgment', $tenant);
    }
}
