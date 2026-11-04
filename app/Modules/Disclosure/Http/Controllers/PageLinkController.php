<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\ChangePageLinkExpiry;
use App\Modules\Disclosure\Actions\DisablePageLink;
use App\Modules\Disclosure\Actions\EnablePageLink;
use App\Modules\Disclosure\Actions\IssuePageLink;
use App\Modules\Disclosure\Actions\RotatePageLink;
use App\Modules\Disclosure\Http\Requests\ExpiryRequest;
use App\Modules\Disclosure\Support\PagePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The link to the page of the workers (criterio C-55): see it, create it, replace it, switch it off and on and set its expiry.
 * Owner and RH only: the link is a credential.
 */
final class PageLinkController extends Controller
{
    public function show(PagePages $pages): Response
    {
        Gate::authorize('manage-disclosure');

        return Inertia::render('disclosure/page', $pages->show());
    }

    public function issue(Request $request, mixed $tenant, IssuePageLink $issue): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $issue->handle($this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace creado.']);

        return redirect()->route('tenant.disclosure.page', $tenant);
    }

    public function rotate(Request $request, mixed $tenant, RotatePageLink $rotate): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $rotate->handle($this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace nuevo. El anterior ya no abre: cambia los carteles.']);

        return redirect()->route('tenant.disclosure.page', $tenant);
    }

    public function disable(Request $request, mixed $tenant, DisablePageLink $disable): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $disable->handle($this->userId($request));
        Inertia::flash('toast', ['type' => 'info', 'message' => 'La página quedó desactivada.']);

        return redirect()->route('tenant.disclosure.page', $tenant);
    }

    public function enable(Request $request, mixed $tenant, EnablePageLink $enable): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $enable->handle($this->userId($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'La página quedó activa.']);

        return redirect()->route('tenant.disclosure.page', $tenant);
    }

    public function expiry(ExpiryRequest $request, mixed $tenant, ChangePageLinkExpiry $change): RedirectResponse
    {
        $change->handle($request->userId(), $request->day());
        Inertia::flash('toast', ['type' => 'success', 'message' => $request->day() === null ? 'El enlace ya no vence.' : 'Vencimiento guardado.']);

        return redirect()->route('tenant.disclosure.page', $tenant);
    }

    private function userId(Request $request): int
    {
        return $request->user()->id ?? abort(403);
    }
}
