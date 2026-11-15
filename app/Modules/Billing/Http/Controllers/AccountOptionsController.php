<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\SearchAccounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The options of the field that picks the account a company moves to: the ones that match what was typed. */
final class AccountOptionsController extends Controller
{
    public function __invoke(Request $request, SearchAccounts $search): JsonResponse
    {
        Gate::authorize('manage-billing');

        return response()->json($search->handle(trim($request->string('q')->toString())));
    }
}
