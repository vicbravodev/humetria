<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ActivateTenant;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\Actions\DeactivateTenant;
use App\Modules\Tenancy\Actions\ListTenants;
use App\Modules\Tenancy\Actions\UpdateTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Enums\TenantView;
use App\Modules\Tenancy\Http\Requests\ActivateTenantRequest;
use App\Modules\Tenancy\Http\Requests\StoreTenantRequest;
use App\Modules\Tenancy\Http\Requests\UpdateTenantRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\DisplayDate;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class TenantConsoleController extends Controller
{
    public function index(Request $request, ListTenants $listTenants): Response
    {
        Gate::authorize('manage-tenants');

        $search = trim($request->string('q')->toString());
        $view = TenantView::tryFrom($request->string('status')->toString()) ?? TenantView::All;
        $summary = $listTenants->summary($search);

        return Inertia::render('tenancy/index', [
            'activationMinDate' => now(config()->string('app.display_timezone'))->addDay()->toDateString(),
            'filters' => ['q' => $search, 'status' => $view->value],
            'counts' => $summary['counts'],
            'trialsEndingSoon' => $summary['trialsEndingSoon'],
            'tenants' => $listTenants->handle($search, $view)->through(function (Tenant $tenant): array {
                $status = $tenant->status();

                return [
                    'id' => $tenant->id,
                    'legal_name' => $tenant->legal_name,
                    'trade_name' => $tenant->trade_name,
                    'rfc' => $tenant->rfc,
                    'slug' => $tenant->slug,
                    'logo_url' => $tenant->logo_path === null ? null : Storage::disk(config()->string('filesystems.tenant_logos'))->url($tenant->logo_path),
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_date' => $this->statusDate($tenant, $status),
                    'active_until' => $tenant->active_until?->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d'),
                    'blocked' => $status->isBlocked(),
                    'owner' => $this->owner($tenant),
                    'invitation' => $this->invitation($tenant),
                ];
            }),
        ]);
    }

    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $createTenant->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa dada de alta.']);

        return redirect()->route('companies.index');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant, UpdateTenant $updateTenant): RedirectResponse
    {
        $updateTenant->handle($tenant, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa actualizada.']);

        return redirect()->route('companies.index');
    }

    public function activate(ActivateTenantRequest $request, Tenant $tenant, ActivateTenant $activateTenant): RedirectResponse
    {
        $until = $request->activeUntil();
        $activateTenant->handle($tenant, $until);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa activa hasta '.$this->day($until).'.']);

        return redirect()->route('companies.index');
    }

    public function deactivate(Tenant $tenant, DeactivateTenant $deactivateTenant): RedirectResponse
    {
        Gate::authorize('manage-tenants');
        abort_if($tenant->status()->isBlocked(), 409);

        $deactivateTenant->handle($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa desactivada.']);

        return redirect()->route('companies.index');
    }

    private function statusDate(Tenant $tenant, TenantStatus $status): ?string
    {
        return match ($status) {
            TenantStatus::NotStarted => null,
            TenantStatus::Trial => 'Prueba hasta '.$this->day($tenant->trialEndsAt()),
            TenantStatus::TrialEnded => 'Prueba terminó el '.$this->day($tenant->trialEndsAt()),
            TenantStatus::Active => 'Activa hasta '.$this->day($tenant->active_until),
            TenantStatus::Expired => 'Venció el '.$this->day($tenant->active_until),
            TenantStatus::Deactivated => 'Desactivada el '.$this->day($tenant->deactivated_at),
        };
    }

    /**
     * @return array{name: string, email: string}|null
     */
    private function owner(Tenant $tenant): ?array
    {
        $user = $tenant->ownerMembership?->user;

        return $user === null ? null : ['name' => $user->name, 'email' => $user->email];
    }

    /**
     * @return array{id: string, name: string, email: string, status: string, status_label: string, expires_on: string}|null
     */
    private function invitation(Tenant $tenant): ?array
    {
        $invitation = $tenant->openInvitation;

        if ($invitation === null) {
            return null;
        }

        $status = $invitation->status();

        return [
            'id' => $invitation->id,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'status' => $status->value,
            'status_label' => $status->label(),
            'expires_on' => DisplayDate::day($invitation->expires_at),
        ];
    }

    private function day(?CarbonInterface $moment): ?string
    {
        return $moment === null ? null : DisplayDate::day($moment);
    }
}
