<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Interventions\Support\ProgramList;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Programas of the company, summarized by state with the closed evaluations a page at a time. Owner, RH and the viewer
 * read it; the Comisión de seguridad e higiene has no part in Chapter 8 of the norm.
 */
final class ProgramListController extends Controller
{
    public function index(ProgramList $list): Response
    {
        Gate::authorize('view-interventions');

        return Inertia::render('interventions/index', $list->page());
    }
}
