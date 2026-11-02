<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Models\ChatConversation;
use App\Modules\Assistant\Models\ChatMessage;
use App\Modules\Assistant\Support\Conversations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes one of the person's own conversations with its messages. The SDK's tables have no way to hide a row, so it is
 * deleted for good: what a person asked Humi is theirs to remove.
 */
final readonly class DeleteConversation
{
    public function __construct(private Conversations $conversations) {}

    /**
     * @throws ConversationNotFound
     */
    public function handle(AssistantScope $scope, string $conversationId): void
    {
        $this->conversations->authorize($scope, $conversationId);

        DB::transaction(function () use ($conversationId): void {
            ChatMessage::query()->where('conversation_id', $conversationId)->delete();
            ChatConversation::query()->whereKey($conversationId)->delete();
        });

        Log::info('assistant.conversation_deleted', ['tenant_id' => $scope->tenantId, 'user_id' => $scope->userId, 'conversation_id' => $conversationId]);
    }
}
