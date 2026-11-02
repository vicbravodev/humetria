<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Actions;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Exceptions\AssistantUnavailable;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Assistant\Support\ScopeGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Whether a message to Humi may go to the provider: Humi is on for the company, its budget of tokens of the calendar month
 * (Mexico City) is not used up, and neither the person nor the company wrote too much in the last minute or day. Asked
 * before the provider is called; a refused message spends no limit.
 */
final readonly class CheckAllowance
{
    private const int MINUTE = 60;

    private const int DAY = 86400;

    public function __construct(private ScopeGuard $guard) {}

    /**
     * @throws AssistantUnavailable
     */
    public function handle(AssistantScope $scope): void
    {
        $this->guard->check($scope);
        $settings = TenantAiSettings::query()->first();

        if ($settings !== null && ! $settings->enabled) {
            $this->refuse($scope, AssistantUnavailable::disabled());
        }

        if ($this->usedThisMonth() >= ($settings->monthly_token_budget ?? config()->integer('humi.monthly_token_budget'))) {
            $this->refuse($scope, AssistantUnavailable::budgetUsed());
        }

        $limits = [
            ["assistant:member:{$scope->participantId}:minute", config()->integer('humi.rate_per_minute'), self::MINUTE],
            ["assistant:member:{$scope->participantId}:day", config()->integer('humi.rate_per_day'), self::DAY],
            ["assistant:tenant:{$scope->tenantId}:minute", config()->integer('humi.tenant_rate_per_minute'), self::MINUTE],
        ];

        foreach ($limits as [$key, $maximum]) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $this->refuse($scope, AssistantUnavailable::tooMany(RateLimiter::availableIn($key)));
            }
        }

        foreach ($limits as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    private function usedThisMonth(): int
    {
        // The month starts at midnight in Mexico City; rows are stored in UTC.
        $start = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfMonth()->utc();
        $row = AiUsage::query()->toBase()->where('created_at', '>=', $start)->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) AS total')->first();

        return filter_var($row->total ?? 0, FILTER_VALIDATE_INT) ?: 0;
    }

    /**
     * @throws AssistantUnavailable
     */
    private function refuse(AssistantScope $scope, AssistantUnavailable $refused): never
    {
        Log::info('assistant.limit_reached', ['tenant_id' => $scope->tenantId, 'user_id' => $scope->userId, 'reason' => $refused->reason]);

        throw $refused;
    }
}
