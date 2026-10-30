<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use InvalidArgumentException;

/**
 * Work inside a company known by its id, where no URL names it: a link that carries its own credential, or a
 * scheduled task that goes through every company.
 */
interface Companies
{
    /**
     * Makes the company the current one, as ResolveTenant does for its routes: its data, logs, audit entries, queued
     * jobs and Spatie team.
     *
     * @throws InvalidArgumentException when no company has that id.
     */
    public function enter(string $tenantId): CompanySummary;

    /**
     * The company the code works in now.
     *
     * @throws NoCurrentTenant outside a company.
     */
    public function current(): CompanySummary;
}
