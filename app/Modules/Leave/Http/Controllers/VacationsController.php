<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\SummaryPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's overview of Vacaciones: owner, hr and viewer read it. The first visit gives the company what it needs to start.
 */
final readonly class VacationsController
{
    public function index(Request $request, LeaveDefaults $defaults, SummaryPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/index', $pages->index($request->string('view')->toString()));
    }
}
