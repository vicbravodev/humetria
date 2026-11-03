<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The start of Compensación: for now, the empty state that points at the roster. */
final class CompensationController extends Controller
{
    public function index(mixed $tenant): Response
    {
        Gate::authorize('view-compensation');

        return Inertia::render('compensation/index');
    }
}
