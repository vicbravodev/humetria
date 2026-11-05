<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Complaints\Actions\DesignateHandler;
use App\Modules\Complaints\Actions\EndHandler;
use App\Modules\Complaints\Http\Requests\DesignateHandlerRequest;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\HandlerPages;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who handles the company's complaints, and who they would reach. Designating or ending a handler is the company's decision;
 * every change is audited and the other handlers are told.
 */
final class HandlerController extends Controller
{
    public function index(mixed $tenant, HandlerPages $pages): Response
    {
        Gate::authorize('manage-complaint-channel');

        return Inertia::render('complaints/handlers', $pages->index());
    }

    public function store(DesignateHandlerRequest $request, mixed $tenant, DesignateHandler $designate, #[CurrentUser] User $user): RedirectResponse
    {
        $designate->handle($request->userId(), $request->workCenterId(), $user->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Persona designada.']);

        // Designating happens on the channel (board V4-Quejas-Designar) or on the list of handlers: back where it was asked.
        return back(fallback: route('tenant.complaints.handlers.index', $tenant));
    }

    public function destroy(mixed $tenant, string $handler, EndHandler $end): RedirectResponse
    {
        Gate::authorize('manage-complaint-channel');
        $found = ComplaintHandler::query()->whereNull('ended_at')->find($handler);
        abort_if($found === null, 404);

        $end->handle($found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Esa persona dejó de atender quejas.']);

        return to_route('tenant.complaints.handlers.index', $tenant);
    }
}
