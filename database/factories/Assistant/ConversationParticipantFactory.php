<?php

declare(strict_types=1);

namespace Database\Factories\Assistant;

use App\Models\User;
use App\Modules\Assistant\Models\ConversationParticipant;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A person of a company who talks to Humi.
 *
 * @extends Factory<ConversationParticipant>
 */
final class ConversationParticipantFactory extends Factory
{
    protected $model = ConversationParticipant::class;

    /**
     * @return array<model-property<ConversationParticipant>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'user_id' => User::factory(),
        ];
    }
}
