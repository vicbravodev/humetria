<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Engagement\Support\PortalClimatePages;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Nos dijeron / Haremos» in the pages of the person of the session: what the company published after the climate survey.
 * A company with nothing published is a 404 that says nothing more.
 */
final class PortalClimateController extends Controller
{
    public function show(string $tenant, WorkerSession $session, PortalClimatePages $pages): Response
    {
        $person = $session->current() ?? abort(404);
        $survey = $pages->survey() ?? abort(404);

        return Inertia::render('engagement/portal-climate', $pages->props($survey, $person, $tenant));
    }
}
