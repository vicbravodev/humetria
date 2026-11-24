<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

use App\Modules\Tenancy\Data\AccessOverview;
use App\Modules\Tenancy\Data\AreaAccess;

/** Who can do what in a company, as Humi tells it: counts by role and one person's own levels, never who holds what. */
interface CompanyAccess
{
    /** The company's roles with how many people hold each, how many are its own (of the maximum) and how many people have extras. */
    public function overview(string $tenantId): AccessOverview;

    /**
     * What the person can do in the company, area by area of «Roles y permisos», for the tools its plan includes.
     *
     * @return list<AreaAccess>
     */
    public function areasOf(string $tenantId, int $userId): array;
}
