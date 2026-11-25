<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

/** Gives a company the accent of its own pages, or takes it off (null) so they go back to the accent of Humetría. */
final readonly class SetTenantBrandColor
{
    public function handle(Tenant $tenant, ?string $color): Tenant
    {
        $tenant->update(['brand_color' => $color === null ? null : strtolower($color)]);

        Log::info('tenant.brand_color_updated', ['tenant_id' => $tenant->id]);

        return $tenant;
    }
}
