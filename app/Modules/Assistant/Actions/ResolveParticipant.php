<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * The participant of a person in the current company, created the first time they talk to Humi.
 */
final readonly class ResolveParticipant
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(int $userId): ConversationParticipant
    {
        // Safe when two requests of the same person arrive together: the unique key lets one create it and the other read it.
        return ConversationParticipant::query()->createOrFirst([
            'tenant_id' => $this->tenant->id(),
            'user_id' => $userId,
        ]);
    }
}
