<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
final class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<model-property<Delivery>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'channel' => 'whatsapp',
            'template' => 'survey_invite_v1',
            'provider_message_id' => 'wamid.'.Str::random(20),
            'recipient_hash' => hash('sha256', Str::random(16)),
            'key_version' => 1,
            'invitation_type' => 'campaign_participant',
            'invitation_id' => (string) Str::uuid(),
            'source_type' => 'campaign',
            'source_id' => (string) Str::uuid(),
            'status' => DeliveryStatus::Created,
            'accepted_at' => null,
        ];
    }

    /** A mail delivery: no template and no phone. */
    public function mail(): self
    {
        return $this->state(['channel' => 'mail', 'template' => null, 'recipient_hash' => null, 'key_version' => null]);
    }
}
