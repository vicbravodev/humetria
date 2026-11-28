<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\DescribeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PlanConsoleController extends Controller
{
    /** The catalog in force and the prices agreed by hand, read only: changing the catalog is a change of code with its version. */
    public function index(Request $request, DescribeCatalog $describe): Response
    {
        Gate::authorize('manage-billing');

        $search = trim($request->string('q')->toString());

        return Inertia::render('billing/plans', [
            'catalog' => $describe->handle(),
            'negotiated' => $describe->negotiated($search),
            'filters' => ['q' => $search],
        ]);
    }
}
