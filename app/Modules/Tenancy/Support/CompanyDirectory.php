<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanyLogo;
use App\Modules\Tenancy\Data\CompanySummary;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CompanyDirectory implements Companies
{
    public function __construct(private TenantContext $tenantContext) {}

    public function enter(string $tenantId): CompanySummary
    {
        $tenant = Str::isUuid($tenantId) ? Tenant::query()->find($tenantId) : null;

        if ($tenant === null) {
            throw new InvalidArgumentException("No company [{$tenantId}].");
        }

        $this->tenantContext->set($tenant);

        return self::summary($tenant);
    }

    public function current(): CompanySummary
    {
        return self::summary(Tenant::query()->findOrFail($this->tenantContext->id()));
    }

    public function contact(): ?string
    {
        return Tenant::query()->findOrFail($this->tenantContext->id())->ownerMembership()->with('user:id,email')->first()?->user?->email;
    }

    private static function summary(Tenant $tenant): CompanySummary
    {
        $logo = $tenant->logo_path === null ? null : new CompanyLogo(
            Storage::disk(config()->string('filesystems.tenant_logos'))->url($tenant->logo_path),
            (int) $tenant->logo_width,
            (int) $tenant->logo_height,
        );

        return new CompanySummary($tenant->id, $tenant->trade_name, $tenant->status()->isBlocked(), $logo);
    }
}
