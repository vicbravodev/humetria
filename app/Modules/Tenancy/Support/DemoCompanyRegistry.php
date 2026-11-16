<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;

final class DemoCompanyRegistry implements DemoCompanies
{
    public function isDemo(string $tenantId): bool
    {
        return $this->id() === $tenantId;
    }

    public function id(): ?string
    {
        $id = Tenant::query()->where('is_demo', true)->value('id');

        return is_string($id) ? $id : null;
    }
}
