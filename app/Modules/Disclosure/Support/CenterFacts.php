<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * What is recorded about one work center that the board judges: the policy in force and how many of its people confirmed it,
 * and which incisos of 5.7 a physical means covered there. An inciso is delivered when the page is active or a means covered
 * it (criterio C-52).
 */
final readonly class CenterFacts
{
    /**
     * @param  array{active: int, confirmed: int}|null  $acknowledgments  The people active today and how many confirmed the version in force; null without a policy.
     * @param  list<string>  $recordedItems  The incisos of 5.7 (a to f) a physical means covered in this center.
     * @param  list<string>  $contents  The incisos of 5.7 (b, c, d, f) for which something is published in this center.
     * @param  string|null  $linkExpiresOn  The day the link of the page expires, written, when it does within the warning term.
     */
    public function __construct(
        public ?PolicyFact $policy,
        public ?array $acknowledgments = null,
        public array $recordedItems = [],
        public bool $pageActive = false,
        public ?string $linkExpiresOn = null,
        public array $contents = [],
        public ?ResultsFact $results = null,
    ) {}

    public function hasContent(string $item): bool
    {
        return in_array($item, $this->contents, true);
    }

    public function delivered(string $item): bool
    {
        return $this->pageActive || in_array($item, $this->recordedItems, true);
    }
}
