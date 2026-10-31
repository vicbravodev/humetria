<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Models\DigestDelivery;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The owner's digest of today.
 *
 * @extends Factory<DigestDelivery>
 */
final class DigestDeliveryFactory extends Factory
{
    protected $model = DigestDelivery::class;

    /**
     * @return array<model-property<DigestDelivery>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'recipient' => 'owner',
            'day' => now()->toDateString(),
            'delivered_at' => now(),
        ];
    }
}
