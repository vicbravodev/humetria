<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\UsageReservation;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageReservation>
 */
final class UsageReservationFactory extends Factory
{
    protected $model = UsageReservation::class;

    /**
     * @return array<model-property<UsageReservation>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'meter' => 'assistant_tokens',
            'period_start' => now(config()->string('app.display_timezone'))->startOfMonth()->toDateString(),
            'idempotency_key' => fake()->uuid(),
            'quantity' => 9000,
            'state' => 'reserved',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
