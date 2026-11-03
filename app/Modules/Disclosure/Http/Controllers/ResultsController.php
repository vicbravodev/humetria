<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\PublishResults;
use App\Modules\Disclosure\Actions\WithdrawResults;
use App\Modules\Disclosure\Support\ResultsPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The results of the closed evaluations that the people can read (num. 7.8; criterio C-54): RH publishes and withdraws them,
 * evaluation by evaluation. Owner and RH only. An evaluation of another company is not found, the same 404 as one that does not
 * exist.
 */
final class ResultsController extends Controller
{
    public function show(ResultsPages $pages): Response
    {
        Gate::authorize('manage-disclosure');

        return Inertia::render('disclosure/results', $pages->show());
    }

    public function publish(Request $request, mixed $tenant, string $campaign, PublishResults $publish): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $publish->handle($campaign, $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Resultados publicados para las personas.']);

        return redirect()->route('tenant.disclosure.results', $tenant);
    }

    public function withdraw(Request $request, mixed $tenant, string $campaign, WithdrawResults $withdraw): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $withdraw->handle($campaign, $request->user()->id ?? abort(403));
        Inertia::flash('toast', ['type' => 'info', 'message' => 'Resultados retirados de la página.']);

        return redirect()->route('tenant.disclosure.results', $tenant);
    }
}
