<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\GrantUsage;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Http\Requests\GrantUsageRequest;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class UsageGrantController extends Controller
{
    public function store(GrantUsageRequest $request, string $company, CompanyRegistry $companies, GrantUsage $grant): RedirectResponse
    {
        abort_if($companies->find($company) === null, 404);

        $grant->handle($company, UsageMeter::ASSISTANT_TOKENS, $request->quantity(), trim($request->string('reason')->toString()), $request->user() ?? abort(401));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tokens agregados al mes.']);

        return redirect()->route('companies.show', $company);
    }
}
