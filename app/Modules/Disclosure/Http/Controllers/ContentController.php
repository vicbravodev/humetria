<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Disclosure\Actions\DiscardDraft;
use App\Modules\Disclosure\Actions\PublishContent;
use App\Modules\Disclosure\Actions\SaveDraft;
use App\Modules\Disclosure\Actions\StartContentDraft;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Http\Requests\ContentPublishRequest;
use App\Modules\Disclosure\Http\Requests\DraftRequest;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Support\ContentPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The blocks of 5.7 the company writes by hand (b, c, d and f; criterio C-52): measures against violence, measures of prevention
 * and control, how to complain and the possible effects on health. Owner and RH only. A draft that is not of the kind in the
 * address, or of another company, is not found.
 */
final class ContentController extends Controller
{
    public function show(ContentPages $pages): Response
    {
        Gate::authorize('manage-disclosure');

        return Inertia::render('disclosure/contents', $pages->show());
    }

    public function start(Request $request, mixed $tenant, string $kind, StartContentDraft $start): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $start->handle($this->kind($kind), $request->user()?->id);

        return redirect()->route('tenant.disclosure.contents', $tenant);
    }

    public function save(DraftRequest $request, mixed $tenant, string $kind, string $document, SaveDraft $save): RedirectResponse
    {
        $save->handle($this->draft($kind, $document)->id, $request->sections());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

        return redirect()->route('tenant.disclosure.contents', $tenant);
    }

    public function publish(ContentPublishRequest $request, mixed $tenant, string $kind, string $document, PublishContent $publish): RedirectResponse
    {
        $published = $publish->handle($this->draft($kind, $document)->id, $request->workCenterIds(), $request->userId());
        Inertia::flash('toast', ['type' => 'success', 'message' => "Publicado: versión {$published->version}."]);

        return redirect()->route('tenant.disclosure.contents', $tenant);
    }

    public function discard(mixed $tenant, string $kind, string $document, DiscardDraft $discard): RedirectResponse
    {
        Gate::authorize('manage-disclosure');
        $discard->handle($this->draft($kind, $document)->id);
        Inertia::flash('toast', ['type' => 'info', 'message' => 'Borrador descartado.']);

        return redirect()->route('tenant.disclosure.contents', $tenant);
    }

    /** The block named in the address: the policy and anything unknown are not found. */
    private function kind(string $kind): DocumentKind
    {
        $found = DocumentKind::tryFrom($kind);

        return $found !== null && $found->isBlock() ? $found : abort(404);
    }

    private function draft(string $kind, string $document): Document
    {
        return Document::query()->where('kind', $this->kind($kind))->findOrFail($document);
    }
}
