<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Http\Controllers;

use App\Models\User;
use App\Modules\Assistant\Actions\CheckAllowance;
use App\Modules\Assistant\Actions\RecordUsage;
use App\Modules\Assistant\Agents\HumiAssistant;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Assistant\Exceptions\ConversationNotFound;
use App\Modules\Assistant\Http\Requests\ChatRequest;
use App\Modules\Assistant\Models\ConversationParticipant;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\Conversations;
use App\Modules\Assistant\Support\HumiTokens;
use App\Modules\Assistant\Support\PageContextResolver;
use App\Modules\Assistant\Support\PersonalDataRedactor;
use App\Modules\Assistant\Support\StopSwitch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * A message to Humi, answered as a stream. The browser sends the last message and, if it continues one, the id of the
 * conversation; the history comes from the server. Before the provider is called the message is checked (whose
 * conversation it is, whether Humi is on and within its limits) and its personal data is masked.
 */
final readonly class ChatController
{
    public function __construct(
        private AssistantScopeFactory $scopes,
        private Conversations $conversations,
        private CheckAllowance $allowance,
        private PageContextResolver $pages,
        private PersonalDataRedactor $redactor,
        private RecordUsage $recordUsage,
        private StopSwitch $stop,
        private HumiTokens $tokens,
    ) {}

    public function store(ChatRequest $request): SymfonyResponse
    {
        $scope = $this->scopes->make($this->user($request));
        $conversationId = $request->conversationId();

        try {
            if ($conversationId !== null) {
                $this->conversations->authorize($scope, $conversationId);
            }

            $this->allowance->handle($scope);
            // The quota of the plan: held before the provider is called, so two messages cannot both spend its last tokens.
            $key = $this->tokens->reserve($scope);
        } catch (ConversationNotFound) {
            abort(404);
        } catch (AssistantUnavailable $refused) {
            return $this->refusal($refused);
        }

        $this->stop->clear($scope->participantId);
        // The answer runs to its end even if the browser leaves, so its usage is always recorded.
        ignore_user_abort(true);
        $startedAt = hrtime(true);

        try {
            $stream = HumiAssistant::make(scope: $scope, page: $this->pages->resolve($request->input('pageContext')))
                ->continueOrStart($conversationId, as: ConversationParticipant::query()->findOrFail($scope->participantId))
                ->stream($this->redactor->redact($request->messageText()))
                ->usingVercelDataProtocol()
                ->then(fn (StreamedAgentResponse $answer) => $this->finish($scope, $key, $answer, $startedAt))
                // An answer that fails midway gives its hold back: the quota is for what was used.
                ->catch(fn (Throwable $failure) => $this->tokens->release($scope, $key));
        } catch (Throwable $failure) {
            $this->tokens->release($scope, $key);

            throw $failure;
        }

        $response = $stream->toResponse($request);
        // A proxy must not hold the answer back until it ends.
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    public function stop(Request $request): Response
    {
        $this->stop->request($this->scopes->make($this->user($request))->participantId);

        return response()->noContent();
    }

    private function finish(AssistantScope $scope, string $key, StreamedAgentResponse $answer, int $startedAt): void
    {
        // What the provider really counted, even above the estimate held.
        $this->tokens->settle($scope, $key, $answer->usage->inputTokens + $answer->usage->outputTokens);

        $tools = [];

        foreach ($answer->toolCalls as $call) {
            if (! in_array($call->name, $tools, true)) {
                $tools[] = $call->name;
            }
        }

        $this->recordUsage->handle(
            $scope,
            $answer->conversationId,
            $answer->usage->inputTokens,
            $answer->usage->outputTokens,
            $tools,
            intdiv(hrtime(true) - $startedAt, 1_000_000),
            $this->stop->requested($scope->participantId),
        );
        $this->stop->clear($scope->participantId);
    }

    private function refusal(AssistantUnavailable $refused): JsonResponse
    {
        return response()->json(
            ['reason' => $refused->reason, 'message' => $refused->getMessage(), 'retry_after' => $refused->retryAfter],
            $refused->reason === AssistantUnavailable::RATE ? 429 : 403,
            $refused->retryAfter === null ? [] : ['Retry-After' => $refused->retryAfter],
        );
    }

    private function user(Request $request): User
    {
        return $request->user() ?? abort(401);
    }
}
