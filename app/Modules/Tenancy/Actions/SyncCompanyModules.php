<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\ModuleSource;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\ModuleStates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final readonly class SyncCompanyModules
{
    public function __construct(private RecordAudit $recordAudit, private ModuleStates $states) {}

    /**
     * Gives the company the modules of its plan. What the plan adds is on at once; what it takes away stays on until the day the
     * change takes effect and then ends, with its row kept so that it comes back, with its data, if a later plan returns it.
     *
     * @param  list<string>  $keys
     *
     * @throws InvalidArgumentException
     */
    public function handle(string $tenantId, array $keys, CarbonImmutable $effectiveOn): void
    {
        $modules = $this->modules($keys);
        $end = CarbonImmutable::parse($effectiveOn->toDateString(), config()->string('app.display_timezone'))->startOfDay()->utc();

        DB::transaction(function () use ($tenantId, $modules, $end): void {
            $rows = TenantModule::query()->where('tenant_id', $tenantId)->whereIn('source', [ModuleSource::Plan, ModuleSource::Legacy])->lockForUpdate()->get();
            $basis = $this->basis($rows->all(), $this->states->hasPlan($tenantId));
            $kept = array_map(fn (Module $module): string => $module->value, $modules);

            foreach ($modules as $module) {
                TenantModule::query()->where('tenant_id', $tenantId)->where('module', $module->value)->where('source', ModuleSource::Legacy)->delete();
                TenantModule::query()->updateOrCreate(['tenant_id' => $tenantId, 'module' => $module->value, 'source' => ModuleSource::Plan], ['expires_at' => null]);
            }

            $lost = array_values(array_filter(Module::cases(), fn (Module $module): bool => in_array($module->value, $basis, true) && ! in_array($module->value, $kept, true)));

            foreach ($lost as $module) {
                $source = $rows->contains(fn (TenantModule $row): bool => $row->module === $module->value && $row->source === ModuleSource::Plan) ? ModuleSource::Plan : ModuleSource::Legacy;
                TenantModule::query()->updateOrCreate(['tenant_id' => $tenantId, 'module' => $module->value, 'source' => $source], ['expires_at' => $end]);
            }

            $changes = ['tenant_id' => $tenantId, 'enabled' => $kept, 'disabled' => array_map(fn (Module $module): string => $module->value, $lost)];
            Log::info('tenant.modules_synced', $changes);
            $this->recordAudit->handle('tenant.modules_synced', null, $changes);
        });

        $this->states->forget($tenantId);
    }

    /**
     * @param  list<string>  $keys
     * @return list<Module> in the catalog's order, without repeats.
     *
     * @throws InvalidArgumentException
     */
    private function modules(array $keys): array
    {
        if ($keys === []) {
            throw new InvalidArgumentException('A plan has at least one module.');
        }

        foreach ($keys as $key) {
            Module::tryFrom($key) ?? throw new InvalidArgumentException("[{$key}] is not a module.");
        }

        return array_values(array_filter(Module::cases(), fn (Module $module): bool => in_array($module->value, $keys, true)));
    }

    /**
     * What the company has by its plan or before it, now: the modules a new plan may be taking away.
     *
     * @param  array<int, TenantModule>  $rows  every plan and legacy row of the company.
     * @return list<string>
     */
    private function basis(array $rows, bool $planned): array
    {
        $now = now();
        $active = array_map(
            fn (TenantModule $row): string => $row->module,
            array_filter($rows, fn (TenantModule $row): bool => $row->expires_at === null || $row->expires_at->greaterThan($now)),
        );

        // A company no plan has spoken for has every module that exists.
        $implicit = $planned ? [] : array_map(fn (Module $module): string => $module->value, array_filter(Module::cases(), fn (Module $module): bool => $module->existsInCode()));

        return array_values(array_unique([...$active, ...$implicit]));
    }
}
