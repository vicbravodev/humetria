<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Models\SeatingDigestDelivery;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The owner's digest of today.
 *
 * @extends Factory<SeatingDigestDelivery>
 */
final class SeatingDigestDeliveryFactory extends Factory
{
    protected $model = SeatingDigestDelivery::class;

    /**
     * @return array<model-property<SeatingDigestDelivery>, mixed>
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
