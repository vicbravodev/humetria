<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Takes a company's logo off its emails, which show its name again, and deletes the file.
 */
final readonly class RemoveTenantLogo
{
    public function handle(Tenant $tenant): Tenant
    {
        $path = $tenant->logo_path;
        $tenant->update(['logo_path' => null, 'logo_width' => null, 'logo_height' => null]);

        if ($path !== null) {
            Storage::disk(config()->string('filesystems.tenant_logos'))->delete($path);
        }

        Log::info('tenant.logo_removed', ['tenant_id' => $tenant->id]);

        return $tenant;
    }
}
