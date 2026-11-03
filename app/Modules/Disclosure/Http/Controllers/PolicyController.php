<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\DiscardDraft;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartPolicyDraft;
use App\Modules\Disclosure\Http\Requests\DraftRequest;
use App\Modules\Disclosure\Support\PolicyPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The policy of prevention (num. 5.1): the draft HR writes from the example of Guía IV. Owner and RH write it; the viewer
 * reads it. A draft of another company is not found, the same 404 as one that does not exist.
 */
final class PolicyController extends Controller
{
    public function show(Request $request, PolicyPages $pages): Response
    {
        Gate::authorize('view-disclosure');

        return Inertia::render('disclosure/policy', $pages->show($request->user()?->can('manage-disclosure') ?? false));
    }

    public function start(Request $request, mixed $tenant, StartPolicyDraft $start): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $start->handle($request->user()?->id);

        return redirect()->route('tenant.disclosure.policy', $tenant);
    }

    public function save(DraftRequest $request, mixed $tenant, string $document, SaveDraft $save): RedirectResponse
    {
        $save->handle($document, $request->sections());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

        return redirect()->route('tenant.disclosure.policy', $tenant);
    }

    public function discard(mixed $tenant, string $document, DiscardDraft $discard): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $discard->handle($document);
        Inertia::flash('toast', ['type' => 'info', 'message' => 'Borrador descartado.']);

        return redirect()->route('tenant.disclosure.policy', $tenant);
    }
}
