<?php

declare(strict_types=1);

namespace App\Modules\Demos\Data;

/**
 * What the ad was shown for, from the final URL suffix of Google Ads: the keyword (`utm_term`), the ad (`utm_content`) and
 * how the search matched it (`matchtype`: e, p or b). Campaign data, never the person's.
 */
final readonly class DemoKeyword
{
    public function __construct(
        public ?string $term = null,
        public ?string $content = null,
        public ?string $matchType = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->term === null && $this->content === null && $this->matchType === null;
    }
}
