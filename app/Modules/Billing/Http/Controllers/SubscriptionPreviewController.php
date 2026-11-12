<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\PreviewSubscription;
use App\Modules\Billing\Exceptions\InvalidSubscription;
use App\Modules\Billing\Http\Requests\PreviewSubscriptionRequest;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** The cost of the plan being typed, before it is saved: the same form, answered with the quote instead of a redirect. */
final class SubscriptionPreviewController extends Controller
{
    public function __invoke(PreviewSubscriptionRequest $request, string $company, CompanyRegistry $companies, PreviewSubscription $preview): JsonResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            return response()->json($preview->handle($company, $request->toData()));
        } catch (InvalidSubscription $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }
    }
}
