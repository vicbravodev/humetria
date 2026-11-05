<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Http\Requests\BenefitRequest;
use App\Modules\Compensation\Support\BenefitPreview;
use App\Modules\Compensation\Support\BenefitValue;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/** What the benefit being typed costs today (decision A14): «Nueva prestación» asks for it as it is answered. Nothing is saved. */
final class BenefitPreviewController extends Controller
{
    public function __invoke(BenefitRequest $request, BenefitPreview $preview): JsonResponse
    {
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $value = BenefitValue::parse($request->string('value')->toString()) ?? 0;

        return response()->json($preview->handle($request->toData(), $value, $request->table() ?: null, $today));
    }
}
