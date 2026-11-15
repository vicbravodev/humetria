<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\PlanSummary;

/** Which plan a company has and for how long, in words and dates and never in money: what its own owner may read. */
interface CompanyPlans
{
    /** The plan the company has now; null when it has none, or its subscription ended. */
    public function current(string $tenantId): ?PlanSummary;
}
