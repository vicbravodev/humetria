<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Data\ModuleRow;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\ModuleSource;
use App\Modules\Tenancy\Models\TenantModule;
use Illuminate\Support\Collection;

/**
 * What a company has of each module right now: the revocation of a person wins; then the plan, what it had before the plan,
 * and a person's grant; and a company with no plan at all keeps every module that exists.
 */
final class ModuleStates
{
    /**
     * @return array<string, ModuleRow> by module key, in the catalog's order.
     */
    public function for(string $tenantId): array
    {
        $rows = TenantModule::query()->where('tenant_id', $tenantId)->active()->get();
        $planned = self::hasPlan($tenantId);
        $states = [];

        foreach (Module::cases() as $module) {
            $states[$module->value] = $this->state($module, $rows->where('module', $module->value), $planned);
        }

        return $states;
    }

    /** Whether a plan ever said what this company has. Without one, nothing is taken from it. */
    public static function hasPlan(string $tenantId): bool
    {
        return TenantModule::query()->where('tenant_id', $tenantId)->where('source', ModuleSource::Plan)->exists();
    }

    /**
     * @param  Collection<int, TenantModule>  $rows  the active ones of this module.
     */
    private function state(Module $module, Collection $rows, bool $planned): ModuleRow
    {
        $revoked = $rows->firstWhere('source', ModuleSource::Revoke);

        if ($revoked instanceof TenantModule) {
            return $this->row($module, false, ModuleSource::Revoke, $revoked);
        }

        foreach ([ModuleSource::Plan, ModuleSource::Legacy, ModuleSource::Grant] as $source) {
            $row = $rows->firstWhere('source', $source);

            if ($row instanceof TenantModule) {
                return $this->row($module, true, $source, $row);
            }
        }

        // No plan has spoken: the company keeps what it can use today.
        return $module->existsInCode() && ! $planned
            ? $this->row($module, true, ModuleSource::Legacy, null)
            : $this->row($module, false, null, null);
    }

    private function row(Module $module, bool $enabled, ?ModuleSource $source, ?TenantModule $row): ModuleRow
    {
        return new ModuleRow(
            $module->value,
            $module->label(),
            $enabled,
            $source?->value,
            $source?->label(),
            $row?->reason,
            $row?->expires_at === null ? null : DisplayDate::day($row->expires_at),
            $module->existsInCode(),
        );
    }
}
