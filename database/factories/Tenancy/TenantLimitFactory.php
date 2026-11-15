<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantLimit>
 */
final class TenantLimitFactory extends Factory
{
    protected $model = TenantLimit::class;

    /**
     * @return array<model-property<TenantLimit>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'resource' => 'seats',
            'amount' => 50,
            'used' => 0,
        ];
    }
}
