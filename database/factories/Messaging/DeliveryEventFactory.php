<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryEvent>
 */
final class DeliveryEventFactory extends Factory
{
    protected $model = DeliveryEvent::class;

    /**
     * The event of a delivery (made here when none is given), of the company of that delivery.
     *
     * @return array<model-property<DeliveryEvent>, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => DeliveryFactory::new(),
            'tenant_id' => fn (array $attributes): string => Delivery::query()->where('id', $attributes['delivery_id'])->firstOrFail()->tenant_id,
            'status' => DeliveryStatus::Sent,
            'failure_cause' => null,
            'occurred_at' => now(),
        ];
    }
}
