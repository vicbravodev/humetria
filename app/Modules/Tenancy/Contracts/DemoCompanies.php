<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Contracts;

/** The demo company: there is at most one, and other modules ask here whether a company is it. */
interface DemoCompanies
{
    public function isDemo(string $tenantId): bool;

    /** The demo company's id; null when there is none. */
    public function id(): ?string;
}
