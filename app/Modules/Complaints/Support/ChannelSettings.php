<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/** What a company decides about its channel: the written procedure, how soon it answers and how long it keeps a case. */
final readonly class ChannelSettings
{
    public function __construct(
        public string $procedure,
        public int $acknowledgeWithinDays,
        public int $resolveWithinDays,
        public int $retentionMonths,
    ) {}

    /** @return array{procedure: string, acknowledge_within_days: int, resolve_within_days: int, retention_months: int} */
    public function attributes(): array
    {
        return [
            'procedure' => $this->procedure,
            'acknowledge_within_days' => $this->acknowledgeWithinDays,
            'resolve_within_days' => $this->resolveWithinDays,
            'retention_months' => $this->retentionMonths,
        ];
    }
}
