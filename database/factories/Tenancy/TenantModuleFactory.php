<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Enums\ModuleSource;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantModule>
 */
final class TenantModuleFactory extends Factory
{
    protected $model = TenantModule::class;

    /**
     * @return array<model-property<TenantModule>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'module' => 'nom035',
            'source' => ModuleSource::Plan,
            'reason' => null,
            'expires_at' => null,
        ];
    }
}
