<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Billing\Contracts\UsageMeter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Humi's tokens against the quota of the company's plan, through Billing's meter: an estimate is held before the provider is called,
 * the real count settles it afterwards and a failure gives it back. The key is made here for each message; neither it nor anything the
 * meter keeps has what was asked or answered.
 */
final readonly class HumiTokens
{
    public function __construct(private UsageMeter $meter) {}

    /**
     * Holds the estimate of one message (what the model may use: its steps times its tokens per step).
     *
     * @return string the key to settle or release it with.
     *
     * @throws AssistantUnavailable when the quota of the month cannot hold it: the provider is not called.
     */
    public function reserve(AssistantScope $scope): string
    {
        $key = Str::uuid()->toString();

        if (! $this->meter->reserve($scope->tenantId, UsageMeter::ASSISTANT_TOKENS, config()->integer('humi.reservation_tokens'), $key)) {
            Log::info('assistant.limit_reached', ['tenant_id' => $scope->tenantId, 'user_id' => $scope->userId, 'reason' => AssistantUnavailable::BUDGET]);

            throw AssistantUnavailable::budgetUsed();
        }

        return $key;
    }

    public function settle(AssistantScope $scope, string $key, int $tokens): void
    {
        $this->meter->settle($scope->tenantId, $key, $tokens);
    }

    public function release(AssistantScope $scope, string $key): void
    {
        $this->meter->release($scope->tenantId, $key);
    }
}
