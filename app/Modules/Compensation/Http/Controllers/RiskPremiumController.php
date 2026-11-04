<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\SaveWorkRiskPremium;
use App\Modules\Compensation\Http\Requests\RiskPremiumRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** The IMSS work risk premium of the company, by vigencia. */
final class RiskPremiumController extends Controller
{
    public function update(RiskPremiumRequest $request, SaveWorkRiskPremium $save): RedirectResponse
    {
        $save->handle($request->effectiveFrom(), $request->rate());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prima de riesgo guardada.']);

        return back();
    }
}
