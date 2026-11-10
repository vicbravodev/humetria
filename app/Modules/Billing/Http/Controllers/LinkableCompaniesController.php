<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\SearchLinkableCompanies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The options of the field that links a company: the ones with no payer yet that match what was typed. */
final class LinkableCompaniesController extends Controller
{
    public function __invoke(Request $request, SearchLinkableCompanies $search): JsonResponse
    {
        Gate::authorize('manage-billing');

        return response()->json($search->handle(trim($request->string('q')->toString())));
    }
}
