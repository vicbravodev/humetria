<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Actions\SyncCompanyModules;
use App\Modules\Tenancy\Contracts\CompanyModules;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class CompanyModuleAccess implements CompanyModules
{
    public function __construct(private ModuleStates $states, private SyncCompanyModules $sync) {}

    public function enabled(string $tenantId, string $module): bool
    {
        $state = $this->states->for($tenantId)[$module] ?? throw new InvalidArgumentException("[{$module}] is not a module.");

        return $state->enabled;
    }

    public function enabledKeys(string $tenantId): array
    {
        return array_values(array_map(
            fn ($state): string => $state->key,
            array_filter($this->states->for($tenantId), fn ($state): bool => $state->enabled),
        ));
    }

    public function restrictedTo(string $tenantId): ?array
    {
        return $this->states->restricted($tenantId) ? $this->enabledKeys($tenantId) : null;
    }

    public function list(string $tenantId): array
    {
        return array_values($this->states->for($tenantId));
    }

    public function syncPlan(string $tenantId, array $modules, CarbonImmutable $effectiveOn): void
    {
        $this->sync->handle($tenantId, $modules, $effectiveOn);
    }
}
