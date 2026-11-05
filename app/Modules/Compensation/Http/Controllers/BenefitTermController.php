<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\SaveContractTerm;
use App\Modules\Compensation\Http\Requests\TermRequest;
use App\Modules\Compensation\Models\Benefit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** The terms in force of a benefit, by vigencia. */
final class BenefitTermController extends Controller
{
    public function store(TermRequest $request, mixed $tenant, string $benefit, SaveContractTerm $save): RedirectResponse
    {
        $save->handle(Benefit::query()->findOrFail($benefit), $request->toData());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Términos guardados.']);

        return back();
    }
}
