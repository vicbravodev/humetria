<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\UsageGrant;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageGrant>
 */
final class UsageGrantFactory extends Factory
{
    protected $model = UsageGrant::class;

    /**
     * @return array<model-property<UsageGrant>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'meter' => 'assistant_tokens',
            'period_start' => now(config()->string('app.display_timezone'))->startOfMonth()->toDateString(),
            'quantity' => 100000,
            'reason' => 'Piloto de un mes',
            'created_at' => now(),
        ];
    }
}
