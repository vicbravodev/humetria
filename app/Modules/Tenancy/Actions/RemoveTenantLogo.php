<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Takes a company's logo off its pages and emails, which show its name again, and deletes the files. The version for dark
 * mode goes with it (it shows nowhere without the one for light); `onlyDark` takes off just that one.
 */
final readonly class RemoveTenantLogo
{
    public function handle(Tenant $tenant, bool $onlyDark = false): Tenant
    {
        $paths = array_filter([$onlyDark ? null : $tenant->logo_path, $tenant->logo_dark_path]);
        $tenant->update([
            ...($onlyDark ? [] : ['logo_path' => null, 'logo_width' => null, 'logo_height' => null]),
            'logo_dark_path' => null,
            'logo_dark_width' => null,
            'logo_dark_height' => null,
        ]);

        Storage::disk(config()->string('filesystems.tenant_logos'))->delete($paths);

        Log::info($onlyDark ? 'tenant.logo_dark_removed' : 'tenant.logo_removed', ['tenant_id' => $tenant->id]);

        return $tenant;
    }
}
