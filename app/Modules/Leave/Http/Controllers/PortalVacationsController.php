<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Support\PortalVacationsPages;
use App\Modules\Leave\Support\PortalVisitor;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Mis vacaciones» in a person's portal, under the same link as the rest: their days by period and their requests. The
 * link is the credential and only ever shows, and acts on, what is of its person.
 */
final class PortalVacationsController extends Controller
{
    public function show(string $token, PortalVisitor $visitor): Response
    {
        $person = $visitor->open($token);

        return Inertia::render('leave/portal', [
            'token' => $token,
            'company' => $person->company,
            'name' => $person->name,
            ...PortalVacationsPages::for($person->employeeId),
        ]);
    }
}
