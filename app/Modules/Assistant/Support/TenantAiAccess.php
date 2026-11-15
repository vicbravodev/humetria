<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\AiAccess;
use App\Modules\Assistant\Models\AiUsage;
use App\Modules\Assistant\Models\TenantAiSettings;
use App\Modules\Tenancy\Contracts\CurrentTenant;

final readonly class TenantAiAccess implements AiAccess
{
    public function __construct(private MonthlyUsage $usage, private PersonalDataRedactor $redactor, private CurrentTenant $tenant) {}

    public function enabled(): bool
    {
        $settings = TenantAiSettings::query()->first();

        return $settings === null || $settings->enabled;
    }

    public function available(): bool
    {
        $settings = TenantAiSettings::query()->first();

        if ($settings !== null && ! $settings->enabled) {
            return false;
        }

        return $this->usage->tokens() < ($settings->monthly_token_budget ?? config()->integer('humi.monthly_token_budget'));
    }

    public function redact(string $text): string
    {
        return $this->redactor->redact($text);
    }

    public function record(string $agent, int $inputTokens, int $outputTokens, int $latencyMs): void
    {
        AiUsage::query()->create([
            'tenant_id' => $this->tenant->id(),
            'agent' => $agent,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'tools' => [],
            'latency_ms' => $latencyMs,
            'stopped' => false,
            'created_at' => now(),
        ]);
    }
}
