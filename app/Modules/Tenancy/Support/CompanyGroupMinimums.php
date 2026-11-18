<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;

/** One instance per request or job (scoped): read once per company, since a page asks for it many times. */
final class CompanyGroupMinimums implements GroupMinimums
{
    /** @var array<string, int|null> by company id */
    private array $memo = [];

    public function __construct(private readonly CurrentTenant $tenant) {}

    public function current(): int
    {
        return $this->own() ?? $this->platformDefault();
    }

    public function own(): ?int
    {
        try {
            $company = $this->tenant->id();
        } catch (NoCurrentTenant) {
            return null;
        }

        if (! array_key_exists($company, $this->memo)) {
            $value = Tenant::query()->whereKey($company)->value('min_group');
            $this->memo[$company] = is_numeric($value) ? (int) $value : null;
        }

        return $this->memo[$company];
    }

    public function platformDefault(): int
    {
        return config()->integer('results.min_group');
    }

    /** Learns the company's choice from the row a request or a job just loaded, so asking costs no query. */
    public function prime(Tenant $tenant): void
    {
        if (array_key_exists('min_group', $tenant->getAttributes())) {
            $this->memo[$tenant->id] = $tenant->min_group;
        }
    }

    /** Whoever changes the company's choice drops what was read, so the same request sees the new one. */
    public function forget(): void
    {
        $this->memo = [];
    }
}
