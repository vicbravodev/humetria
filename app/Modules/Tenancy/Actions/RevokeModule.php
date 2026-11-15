<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\ModuleSource;
use App\Modules\Tenancy\Exceptions\InvalidModuleChange;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\ModuleStates;
use App\Modules\Tenancy\Support\OwnerNotices;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RevokeModule
{
    public function __construct(private RecordAudit $recordAudit, private ModuleStates $states, private OwnerNotices $notices) {}

    /**
     * Takes a module away from the company by hand, with the reason: a grant is ended, and if its plan (or what it had before one)
     * still gives it, a revocation is written that wins over them until someone grants it again. Nothing of its data is touched.
     *
     * @throws InvalidModuleChange
     */
    public function handle(Tenant $tenant, string $key, string $reason, User $by): void
    {
        $module = Module::tryFrom($key) ?? throw new InvalidModuleChange('module', 'Elige un módulo de la lista.');
        $reason = trim($reason);
        $this->states->forget($tenant->id);

        if ($reason === '') {
            throw new InvalidModuleChange('reason', 'Di por qué se quita el módulo.');
        }

        if (! $this->states->for($tenant->id)[$module->value]->enabled) {
            throw new InvalidModuleChange('module', 'La empresa no tiene ese módulo.');
        }

        DB::transaction(function () use ($tenant, $module, $reason, $by): void {
            TenantModule::query()->where('tenant_id', $tenant->id)->where('module', $module->value)->where('source', ModuleSource::Grant)->delete();
            $this->states->forget($tenant->id);

            if ($this->states->for($tenant->id)[$module->value]->enabled) {
                TenantModule::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module' => $module->value, 'source' => ModuleSource::Revoke],
                    ['reason' => $reason, 'expires_at' => null, 'granted_by' => $by->id],
                );
            }

            Log::info('tenant.module_revoked', ['tenant_id' => $tenant->id, 'module' => $module->value]);
            $this->recordAudit->handle('tenant.module_revoked', $tenant, ['tenant_id' => $tenant->id, 'module' => $module->value, 'reason' => $reason]);
        });

        $this->states->forget($tenant->id);
        $this->notices->toolsChanged($tenant, [], [$module], CarbonImmutable::now());
    }
}
