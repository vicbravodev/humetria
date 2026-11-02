<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Models\User;
use App\Modules\Assistant\Actions\DeleteConversation;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\Conversations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Ai\Storage\StoredMessage;

final readonly class ConversationController
{
    private const int PER_PAGE = 15;

    private const int MESSAGES_PER_PAGE = 30;

    public function __construct(
        private AssistantScopeFactory $scopes,
        private Conversations $conversations,
        private DeleteConversation $delete,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->conversations->page($this->scopes->make($this->user($request)), self::PER_PAGE));
    }

    public function show(Request $request, string $tenant, string $conversation): JsonResponse
    {
        $scope = $this->scopes->make($this->user($request));
        $cursor = $request->query('cursor');

        try {
            $title = $this->conversations->title($scope, $conversation);
            $page = $this->conversations->messages($scope, $conversation, self::MESSAGES_PER_PAGE, is_string($cursor) ? $cursor : null);
        } catch (ConversationNotFound) {
            abort(404);
        }

        return response()->json([
            'id' => $conversation,
            'title' => $title,
            'messages' => array_map(fn (StoredMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->createdAt?->toIso8601String(),
            ], $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function destroy(Request $request, string $tenant, string $conversation): Response
    {
        try {
            $this->delete->handle($this->scopes->make($this->user($request)), $conversation);
        } catch (ConversationNotFound) {
            abort(404);
        }

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        return $request->user() ?? abort(401);
    }
}
