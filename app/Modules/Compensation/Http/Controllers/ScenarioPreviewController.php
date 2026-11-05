<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Http\Requests\ScenarioRequest;
use App\Modules\Compensation\Support\ScenarioPreview;
use Illuminate\Http\JsonResponse;

/** The live cost of the scenario being typed (decision A12): the editor asks for it as the values move. Nothing is saved. */
final class ScenarioPreviewController extends Controller
{
    public function __invoke(ScenarioRequest $request, ScenarioPreview $preview): JsonResponse
    {
        return response()->json($preview->handle($request->toData()));
    }
}
