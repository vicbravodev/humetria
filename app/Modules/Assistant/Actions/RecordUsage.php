<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Models\AiUsage;
use Illuminate\Support\Facades\Log;

/**
 * Records one message to Humi when its answer ends, or is stopped: the tokens, the tools it called and how long it took,
 * for the person and the company. The log gets ids and counts; what was said goes to neither.
 */
final readonly class RecordUsage
{
    private const string AGENT = 'HumiAssistant';

    /**
     * @param  list<string>  $tools  the tools the answer called, by name
     */
    public function handle(AssistantScope $scope, ?string $conversationId, int $inputTokens, int $outputTokens, array $tools, int $latencyMs, bool $stopped): AiUsage
    {
        $usage = AiUsage::query()->create([
            'tenant_id' => $scope->tenantId,
            'participant_id' => $scope->participantId,
            'conversation_id' => $conversationId,
            'agent' => self::AGENT,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'tools' => $tools,
            'latency_ms' => $latencyMs,
            'stopped' => $stopped,
            'created_at' => now(),
        ]);

        Log::info($stopped ? 'assistant.message_stopped' : 'assistant.message_sent', [
            'tenant_id' => $scope->tenantId,
            'user_id' => $scope->userId,
            'conversation_id' => $conversationId,
            'tokens' => $inputTokens + $outputTokens,
            'tools' => count($tools),
        ]);

        return $usage;
    }
}
