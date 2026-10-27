<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Actions\ListTenants;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TenantConsoleController extends Controller
{
    public function index(ListTenants $listTenants): Response
    {
        Gate::authorize('manage-tenants');

        return Inertia::render('tenancy/index', [
            'tenants' => $listTenants->handle()->through(function (Tenant $tenant): array {
                $status = $tenant->status();

                return [
                    'id' => $tenant->id,
                    'legal_name' => $tenant->legal_name,
                    'trade_name' => $tenant->trade_name,
                    'rfc' => $tenant->rfc,
                    'slug' => $tenant->slug,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_date' => $this->statusDate($tenant, $status),
                    'active_until' => $tenant->active_until?->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d'),
                    'blocked' => $status->isBlocked(),
                ];
            }),
        ]);
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

    private function day(?CarbonInterface $moment): ?string
    {
        // Carbon's Spanish months end in a dot ("oct."); the design system writes "27 oct 2026".
        return $moment === null ? null : str_replace('.', '', $moment->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY'));
    }
}
