<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * The participant of a person in the current company, created the first time they talk to Humi.
 */
final readonly class ResolveParticipant
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(int $userId): ConversationParticipant
    {
        $tenantId = $this->tenant->id();

        // Safe when two requests of the same person arrive together: the unique key lets one create it and the other read it.
        $participant = ConversationParticipant::query()->createOrFirst(['tenant_id' => $tenantId, 'user_id' => $userId]);

        if ($participant->wasRecentlyCreated) {
            Log::info('assistant.participant_created', ['tenant_id' => $tenantId, 'user_id' => $userId]);
        }

        return $participant;
    }
}
