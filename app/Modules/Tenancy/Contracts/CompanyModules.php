<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\ModuleRow;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The modules a company has: the keys of the catalog (attendance, nom035, nom037, seating, complaints…). A company with no
 * plan keeps every module that exists, so nothing is lost until someone assigns one. Billing says what a plan includes; Tenancy
 * decides what a company may use.
 */
interface CompanyModules
{
    /**
     * @throws InvalidArgumentException when it is not a module.
     */
    public function enabled(string $tenantId, string $module): bool;

    /**
     * The modules the company has now, in the catalog's order.
     *
     * @return list<string>
     */
    public function enabledKeys(string $tenantId): array;

    /**
     * The only modules the company may use, or null when nothing restricts it (no plan, nothing taken away by hand).
     *
     * @return list<string>|null
     */
    public function restrictedTo(string $tenantId): ?array;

    /**
     * Every module with whether the company has it and why.
     *
     * @return list<ModuleRow>
     */
    public function list(string $tenantId): array;

    /**
     * Gives the company the modules of its plan: the ones it adds are on at once, the ones it takes away stay on until the day the
     * change takes effect (switched off by an end date, never deleted, so they come back with their data).
     *
     * @param  list<string>  $modules
     *
     * @throws InvalidArgumentException when the list is empty or has something that is not a module.
     */
    public function syncPlan(string $tenantId, array $modules, CarbonImmutable $effectiveOn): void;
}
