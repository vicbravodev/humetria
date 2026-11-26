<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/** The plans of the catalog by key and name, and the key of the one a company holds: for speaking to the companies of one plan. */
interface PlanNames
{
    /** @return array<string, string> every plan of the catalog, key => name */
    public function all(): array;

    /** The key of the plan the company has now; null when it has none, or its subscription ended. */
    public function keyOf(string $tenantId): ?string;
}
