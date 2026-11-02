<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * One norm in the company's compliance overview. `state` is up_to_date, in_progress, pending or not_applicable;
 * `stateLabel` says it in the panel's words; `summary` gives the counts behind it and `link` the screen to act on it.
 */
final readonly class ComplianceStatus
{
    public function __construct(
        public string $key,
        public string $label,
        public string $state,
        public string $stateLabel,
        public string $summary,
        public string $link,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'state' => $this->state, 'stateLabel' => $this->stateLabel, 'summary' => $this->summary, 'link' => $this->link];
    }
}
