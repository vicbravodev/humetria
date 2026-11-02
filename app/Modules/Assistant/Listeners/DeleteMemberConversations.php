<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Listeners;

use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Events\MemberRemoved;
use Illuminate\Support\Facades\Log;

/** Someone who lost their access to a company keeps none of what they asked Humi there: their conversations and their participant go. */
final readonly class DeleteMemberConversations
{
    public function __construct(private Companies $companies) {}

    public function handle(MemberRemoved $event): void
    {
        $this->companies->enter($event->tenantId);
        $participant = ConversationParticipant::query()->where('user_id', $event->userId)->first();

        if ($participant === null) {
            return;
        }

        $participant->messages()->delete();
        $conversations = $participant->conversations()->delete();
        $participant->delete();

        Log::info('assistant.member_conversations_deleted', ['tenant_id' => $event->tenantId, 'user_id' => $event->userId, 'conversations' => $conversations]);
    }
}
