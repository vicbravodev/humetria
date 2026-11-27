<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\RewindDemoStep;
use App\Modules\Demos\Actions\SkipDemoStep;
use App\Modules\Demos\Http\Requests\DemoGuideRequest;
use Illuminate\Http\JsonResponse;

/**
 * What the dock of the guide calls: the guide as the panel shares it (`null` once the session is over; the poll records no
 * visit), and «Saltar paso» or going back to a step, which answer with the guide as it stands then.
 */
final class DemoGuideController extends Controller
{
    public function show(DemoGuideRequest $request): JsonResponse
    {
        return $this->guide($request);
    }

    public function skip(DemoGuideRequest $request, string $step, SkipDemoStep $skip): JsonResponse
    {
        abort_unless($skip->handle($step), 404);

        return $this->guide($request);
    }

    public function rewind(DemoGuideRequest $request, string $step, RewindDemoStep $rewind): JsonResponse
    {
        abort_unless($rewind->handle($step), 404);

        return $this->guide($request);
    }

    /** The guide as it stands after the change, so the dock need not wait for its next poll. */
    private function guide(DemoGuideRequest $request): JsonResponse
    {
        $guide = $request->guide()->forCurrentRequest();

        // A literal null, not the empty object Laravel makes of it.
        return $guide === null ? JsonResponse::fromJsonString('null') : new JsonResponse($guide);
    }
}
