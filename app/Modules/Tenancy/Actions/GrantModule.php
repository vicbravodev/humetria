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

final readonly class GrantModule
{
    public function __construct(private RecordAudit $recordAudit, private ModuleStates $states, private OwnerNotices $notices) {}

    /**
     * Gives the company a module it does not have, with the reason and, optionally, the last day: a hand-made revocation of
     * that module is lifted, and if the plan does not give it either, a grant is written. Nothing is deleted when it ends.
     *
     * @throws InvalidModuleChange
     */
    public function handle(Tenant $tenant, string $key, string $reason, ?CarbonImmutable $until, User $by): void
    {
        $module = Module::tryFrom($key) ?? throw new InvalidModuleChange('module', 'Elige un módulo de la lista.');
        $reason = trim($reason);
        $this->states->forget($tenant->id);

        if ($reason === '') {
            throw new InvalidModuleChange('reason', 'Di por qué se concede el módulo.');
        }

        if ($this->states->for($tenant->id)[$module->value]->enabled) {
            throw new InvalidModuleChange('module', 'La empresa ya tiene ese módulo.');
        }

        $last = $until?->toDateString();
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString();

        if ($last !== null && $last < $today) {
            throw new InvalidModuleChange('until', 'El último día no puede ser anterior a hoy.');
        }

        DB::transaction(function () use ($tenant, $module, $reason, $last, $by): void {
            TenantModule::query()->where('tenant_id', $tenant->id)->where('module', $module->value)->where('source', ModuleSource::Revoke)->delete();
            $this->states->forget($tenant->id);

            // Lifting the revocation may be all it takes: the plan, or what the company had, gives it back.
            if (! $this->states->for($tenant->id)[$module->value]->enabled) {
                // The last day counts: the grant ends when the day after it begins, in the time zone the panel shows.
                $ends = $last === null ? null : CarbonImmutable::parse($last, config()->string('app.display_timezone'))->addDay()->startOfDay()->utc();
                TenantModule::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'module' => $module->value, 'source' => ModuleSource::Grant],
                    ['reason' => $reason, 'expires_at' => $ends, 'granted_by' => $by->id],
                );
            }

            Log::info('tenant.module_granted', ['tenant_id' => $tenant->id, 'module' => $module->value, 'until' => $last]);
            $this->recordAudit->handle('tenant.module_granted', $tenant, ['tenant_id' => $tenant->id, 'module' => $module->value, 'reason' => $reason, 'until' => $last]);
        });

        $this->states->forget($tenant->id);
        $this->notices->toolsChanged($tenant, [$module], [], null);
    }
}
