<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\UsageCounter;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
final class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    /**
     * @return array<model-property<UsageCounter>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'meter' => 'assistant_tokens',
            'period_start' => now(config()->string('app.display_timezone'))->startOfMonth()->toDateString(),
            'used' => 0,
            'reserved' => 0,
            'quota' => null,
        ];
    }
}
