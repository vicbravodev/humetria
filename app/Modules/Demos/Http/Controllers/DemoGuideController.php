<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Http\Requests\DemoGuideRequest;
use Illuminate\Http\JsonResponse;

/** What the dock of the guide polls: the guide as the panel shares it, `null` once the session is over. Records no visit. */
final class DemoGuideController extends Controller
{
    public function show(DemoGuideRequest $request): JsonResponse
    {
        return $this->guide($request);
    }

    private function guide(DemoGuideRequest $request): JsonResponse
    {
        $guide = $request->guide()->forCurrentRequest();

        // A literal null, not the empty object Laravel makes of it.
        return $guide === null ? JsonResponse::fromJsonString('null') : new JsonResponse($guide);
    }
}
