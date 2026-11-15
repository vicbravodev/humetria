<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\UsageEvent;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageEvent>
 */
final class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    /**
     * @return array<model-property<UsageEvent>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'meter' => 'assistant_tokens',
            'period_start' => now(config()->string('app.display_timezone'))->startOfMonth()->toDateString(),
            'quantity' => 1200,
            'idempotency_key' => fake()->uuid(),
            'occurred_at' => now(),
        ];
    }
}
