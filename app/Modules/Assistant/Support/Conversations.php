<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Models\ChatConversation;
use App\Modules\Assistant\Models\ConversationParticipant;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\PaginatesConversations;
use Laravel\Ai\Contracts\VerifiesConversationOwnership;
use Laravel\Ai\Storage\StoredMessage;
use LogicException;

/**
 * The conversations a person has with Humi in a company, the one place that decides whose a conversation is. The SDK
 * does not check ownership when it continues or reads one: everything that touches a conversation by its id asks here
 * first. The participant exists only inside its company, so a conversation of another company is never the person's.
 */
final readonly class Conversations
{
    public const string PARTICIPANT = 'assistant_participant';

    public function __construct(private ConversationStore $store) {}

    /**
     * @throws ConversationNotFound
     */
    public function authorize(AssistantScope $scope, string $conversationId): void
    {
        $store = $this->store;

        if (! $store instanceof VerifiesConversationOwnership) {
            throw new LogicException('The conversation store of the SDK does not verify ownership.');
        }

        $participantExists = ConversationParticipant::query()->whereKey($scope->participantId)->exists();

        if (! $participantExists || ! $store->conversationBelongsTo($conversationId, self::PARTICIPANT, $scope->participantId)) {
            throw new ConversationNotFound;
        }
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, title: string, updated_at: string}>
     */
    public function page(AssistantScope $scope, int $perPage): LengthAwarePaginator
    {
        return $this->ofParticipant($scope)
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->through(fn (ChatConversation $conversation): array => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'updated_at' => $conversation->updated_at->toIso8601String(),
            ]);
    }

    /**
     * @throws ConversationNotFound
     */
    public function title(AssistantScope $scope, string $conversationId): string
    {
        $this->authorize($scope, $conversationId);

        return $this->ofParticipant($scope)->whereKey($conversationId)->firstOrFail()->title;
    }

    /**
     * @return Builder<ChatConversation>
     */
    private function ofParticipant(AssistantScope $scope): Builder
    {
        // The participant is of this company only if its row is visible in it.
        ConversationParticipant::query()->findOrFail($scope->participantId);

        return ChatConversation::query()->where('participant_type', self::PARTICIPANT)->where('participant_id', $scope->participantId);
    }

    /**
     * The messages, the latest first, a page at a time.
     *
     * @return CursorPaginator<int, StoredMessage>
     *
     * @throws ConversationNotFound
     */
    public function messages(AssistantScope $scope, string $conversationId, int $perPage, ?string $cursor): CursorPaginator
    {
        $this->authorize($scope, $conversationId);
        $store = $this->store;

        if (! $store instanceof PaginatesConversations) {
            throw new LogicException('The conversation store of the SDK does not paginate messages.');
        }

        return $store->paginateConversationMessages($conversationId, $perPage, 'cursor', $cursor === null ? null : Cursor::fromEncoded($cursor));
    }
}
