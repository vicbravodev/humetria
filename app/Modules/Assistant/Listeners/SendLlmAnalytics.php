<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Listeners;

use App\Modules\Assistant\Agents\HumiAssistant;
use App\Support\SendPostHogEvent;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * Every call to a model, as PostHog's LLM analytics `$ai_generation`: model, provider, tokens, time and whether it failed,
 * for every agent of every module. Only Humi's carry what was said (the whole prompt and the answer, after
 * PersonalDataRedactor masked the person's message), so the assistant can be improved: any other agent works on what
 * people wrote anonymously (the comments of a climate survey) and sends its numbers only. Not tied to a person: the user
 * is a keyed hash and PostHog keeps no profile. Queued and failure-safe (SendPostHogEvent::safely).
 */
final class SendLlmAnalytics
{
    /** @var array<string, float> When each call in course started, by invocation. */
    private array $started = [];

    public function started(PromptingAgent|StreamingAgent $event): void
    {
        $this->started[$event->invocationId] = microtime(true);
    }

    public function answered(AgentPrompted $event): void
    {
        $response = $event->response;
        $tools = array_values(array_unique($response->toolCalls->map(fn (ToolCall $call): string => $call->name)->all()));

        $this->send($event->invocationId, $event->prompt, $response->conversationId, [
            '$ai_input_tokens' => $response->usage->inputTokens,
            '$ai_output_tokens' => $response->usage->outputTokens,
            '$ai_stream' => $event instanceof AgentStreamed,
            '$ai_is_error' => false,
            'tools_called' => $tools,
        ], [
            '$ai_output_choices' => [['role' => 'assistant', 'content' => $response->text]],
        ]);
    }

    public function failed(AgentFailed $event): void
    {
        // The class only: a provider's error can quote the prompt.
        $this->send($event->invocationId, $event->prompt, null, ['$ai_is_error' => true, '$ai_error' => $event->exception::class], []);
    }

    /**
     * @param  array<string, mixed>  $numbers  what every agent sends
     * @param  array<string, mixed>  $said  what only Humi sends, besides its prompt
     */
    private function send(string $invocationId, AgentPrompt $prompt, ?string $conversationId, array $numbers, array $said): void
    {
        $started = $this->started[$invocationId] ?? null;
        unset($this->started[$invocationId]);
        $humi = $prompt->agent instanceof HumiAssistant;

        SendPostHogEvent::safely('$ai_generation', self::distinctId(), [
            '$ai_trace_id' => $conversationId ?? $invocationId,
            '$ai_span_id' => $invocationId,
            '$ai_span_name' => class_basename($prompt->agent),
            '$ai_model' => $prompt->model,
            '$ai_provider' => $prompt->provider->name(),
            '$ai_latency' => $started === null ? null : microtime(true) - $started,
            ...$numbers,
            ...($humi ? [
                '$ai_input' => [
                    ['role' => 'system', 'content' => (string) $prompt->agent->instructions()],
                    ['role' => 'user', 'content' => $prompt->prompt],
                ],
                ...$said,
            ] : []),
            'tenant_id' => Context::get('tenant_id'),
            'request_id' => Context::get('request_id'),
            '$process_person_profile' => false,
            '$lib' => 'humetria-php',
        ]);
    }

    /** The same person under the same id, which neither PostHog nor anyone reading it can turn back into the user. */
    private static function distinctId(): string
    {
        $user = Context::get('user_id');

        return is_int($user) || is_string($user)
            ? hash_hmac('sha256', 'posthog:'.$user, config()->string('app.key'))
            : 'humetria-server:'.Str::uuid();
    }
}
