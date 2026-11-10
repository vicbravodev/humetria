<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\ArchivePolicy;
use App\Modules\Leave\Actions\SavePolicy;
use App\Modules\Leave\Http\Requests\PolicyRequest;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\PolicyPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The vacation policies of the company: owner, hr and viewer read them; owner and hr write them. A policy of another
 * company is a 404.
 */
final readonly class PolicyController
{
    public function index(LeaveDefaults $defaults, PolicyPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/policies', [...$pages->index(), 'canManage' => Gate::allows('manage-vacations')]);
    }

    public function create(LeaveDefaults $defaults, PolicyPages $pages): Response
    {
        Gate::authorize('manage-vacations');
        $defaults->ensure();

        return Inertia::render('leave/policy', $pages->form(null));
    }

    public function store(PolicyRequest $request, mixed $tenant, SavePolicy $save): RedirectResponse
    {
        $save->handle($request->policy());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Política creada.']);

        return to_route('tenant.vacations.policies.index', ['tenant' => $tenant]);
    }

    public function edit(mixed $tenant, string $policy, LeaveDefaults $defaults, PolicyPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/policy', [...$pages->form(LeavePolicy::query()->with('rows')->findOrFail($policy)), 'canManage' => Gate::allows('manage-vacations')]);
    }

    public function update(PolicyRequest $request, mixed $tenant, string $policy, SavePolicy $save): RedirectResponse
    {
        $save->handle($request->policy(), LeavePolicy::query()->findOrFail($policy)->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Política guardada. Los periodos ya otorgados conservan sus términos.']);

        return to_route('tenant.vacations.policies.index', ['tenant' => $tenant]);
    }

    public function archive(mixed $tenant, string $policy, ArchivePolicy $archive): RedirectResponse
    {
        Gate::authorize('manage-vacations');
        $archive->handle(LeavePolicy::query()->findOrFail($policy)->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Política archivada.']);

        return back();
    }
}
