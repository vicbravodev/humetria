<?php

declare(strict_types=1);

namespace App\Modules\Results\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Results\Enums\HowTold;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Support\ResultsClock;
use App\Modules\Telework\Contracts\PortalAccess;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The message a person reads in their portal when RH channeled them through it (board V4-N035-Canalizacion, «Aviso en su
 * portal»), as «usted»: that the company offers support, where it proposes they go and that it is confidential. The latest
 * one only, never the note RH wrote nor anything of the Guía I. A link that is not valid, or one with no message, is a 404
 * that says nothing about whether it ever existed.
 */
final class PortalSupportController extends Controller
{
    public function show(string $token, PortalAccess $access): Response
    {
        $person = $access->open($token) ?? abort(404);
        $referral = ScreeningReferral::query()->where('employee_id', $person->employeeId)->where('told', HowTold::Portal)
            ->orderByDesc('referred_on')->orderByDesc('updated_at')->first(['destination', 'referred_on']) ?? abort(404);

        return Inertia::render('results/portal-support', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            'on' => ResultsClock::day(CarbonImmutable::parse($referral->referred_on, config()->string('app.display_timezone'))),
            'message' => $referral->destination->notice(),
        ]);
    }
}
