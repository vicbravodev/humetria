<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Models\AiFeedback;
use App\Modules\Assistant\Models\ChatMessage;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\Conversations;
use Illuminate\Support\Facades\Log;

/**
 * A person's thumb on an answer of Humi: only on an answer of their own conversations, one per answer (a new one replaces
 * the last). A message that is not theirs, is not an answer or does not exist is the same: not found.
 */
final readonly class RateMessage
{
    /**
     * @throws ConversationNotFound
     */
    public function handle(AssistantScope $scope, string $messageId, string $rating, ?string $reason): void
    {
        $message = ConversationParticipant::query()->whereKey($scope->participantId)->exists()
            ? ChatMessage::query()->whereKey($messageId)->where('participant_type', Conversations::PARTICIPANT)->where('participant_id', $scope->participantId)->where('role', 'assistant')->first()
            : null;

        if ($message === null) {
            throw new ConversationNotFound;
        }

        AiFeedback::query()->updateOrCreate(
            ['participant_id' => $scope->participantId, 'message_id' => $message->id],
            ['tenant_id' => $scope->tenantId, 'conversation_id' => $message->conversation_id, 'rating' => $rating, 'reason' => $reason, 'created_at' => now()],
        );

        Log::info('assistant.message_rated', ['tenant_id' => $scope->tenantId, 'user_id' => $scope->userId, 'conversation_id' => $message->conversation_id, 'rating' => $rating]);
    }
}
