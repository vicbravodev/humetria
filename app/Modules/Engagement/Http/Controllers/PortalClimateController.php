<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Support\PortalClimatePages;
use App\Modules\Telework\Contracts\PortalAccess;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Nos dijeron / Haremos» in a person's portal, under the same link as the rest: what the company published after the
 * climate survey. A link that is not valid, or a company with nothing published, is a 404 that says nothing more.
 */
final class PortalClimateController extends Controller
{
    public function show(string $token, PortalAccess $access, PortalClimatePages $pages): Response
    {
        $person = $access->open($token) ?? abort(404);
        $survey = $pages->survey() ?? abort(404);

        return Inertia::render('engagement/portal-climate', $pages->props($survey, $person, $token));
    }
}
