<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Support\ResultsClock;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The message the person of the session reads in their pages when RH channeled them through the portal (board
 * V4-N035-Canalizacion, «Aviso en su portal»), as «usted»: that the company offers support, where it proposes they go and
 * that it is confidential. The latest one only, never the note RH wrote nor anything of the Guía I. A person with no
 * message gets a 404 that says nothing more.
 */
final class PortalSupportController extends Controller
{
    public function show(string $tenant, WorkerSession $session): Response
    {
        $person = $session->current() ?? abort(404);
        $referral = ScreeningReferral::query()->where('employee_id', $person->employeeId)->where('told', HowTold::Portal)
            ->orderByDesc('referred_on')->orderByDesc('updated_at')->first(['destination', 'referred_on']) ?? abort(404);

        return Inertia::render('results/portal-support', [
            'slug' => $tenant,
            'company' => $person->companyName,
            'name' => $person->personName,
            'on' => ResultsClock::day(CarbonImmutable::parse($referral->referred_on, config()->string('app.display_timezone'))),
            'message' => $referral->destination->notice(),
        ]);
    }
}
