<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Modules\Messaging\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
final class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<model-property<WebhookEvent>, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'meta',
            'event_id' => fake()->uuid(),
            'received_at' => now(),
            'orphan' => null,
            'processed_at' => null,
        ];
    }
}
