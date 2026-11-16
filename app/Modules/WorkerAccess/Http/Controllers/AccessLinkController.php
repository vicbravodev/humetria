<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Actions\OpenAccessLink;
use App\Modules\WorkerAccess\Actions\RequestAccessLink;
use App\Modules\WorkerAccess\Enums\LinkKind;
use App\Modules\WorkerAccess\Http\Requests\RequestLinkRequest;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One-time links: a person asks for one by email (the answer is always the same), opens it (a «Continuar» that spends nothing,
 * since mail filters open links ahead of the person) and spends it (a session opens). A link that does not work shows one
 * screen, whatever the cause.
 */
final readonly class AccessLinkController
{
    public function __construct(private Companies $companies, private Roster $roster) {}

    public function request(RequestLinkRequest $request, RequestAccessLink $ask): JsonResponse
    {
        $ask->handle($request->email());

        return response()->json(['requested' => true])->header('Cache-Control', 'no-store, private');
    }

    public function show(string $tenant, string $token): Response
    {
        return Inertia::render('worker-access/link', [
            'company' => $this->companies->current()->tradeName,
            // The token stays in the address only: «Continuar» posts to the page's own address.
            'opens' => $this->opens($token),
            'homeUrl' => route('worker.home', $tenant),
        ]);
    }

    public function open(string $tenant, string $token, OpenAccessLink $open, WorkerSessionStore $sessions): RedirectResponse
    {
        $link = $open->handle($token);

        if ($link === null) {
            return redirect()->route('worker.link.show', [$tenant, $token]);
        }

        $sessions->start($link->employee_id, $link->kind->entry());
        Log::info('worker_access.entered', ['employee_id' => $link->employee_id, 'via' => $link->kind->entry()->value]);

        // A link RH issued is for registering a key; one the person asked for goes straight to their start.
        return redirect()->route($link->kind === LinkKind::Issued ? 'worker.key.show' : 'worker.home', $tenant);
    }

    /** Whether the link would still open, without spending it: the same rule as OpenAccessLink. */
    private function opens(string $token): bool
    {
        $link = WorkerAccessLink::findPending($token);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        return $person !== null && $person->active;
    }
}
