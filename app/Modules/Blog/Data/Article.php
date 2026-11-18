<?php

declare(strict_types=1);

namespace App\Modules\Blog\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** One blog article, read from its Markdown file. `reviewedAt` null is a draft: nobody has signed it off yet. */
final readonly class Article
{
    /** @param list<string> $sources official sources the article cites (https URLs of gob.mx) */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public CarbonImmutable $publishedAt,
        public CarbonImmutable $updatedAt,
        public ?string $reviewedBy,
        public ?CarbonImmutable $reviewedAt,
        public array $sources,
        public string $html,
        public int $readingMinutes,
    ) {}

    /** Published means reviewed by the owner and dated today or earlier. */
    public function isPublished(DateTimeInterface $now): bool
    {
        return $this->reviewedAt !== null && $this->publishedAt <= $now;
    }
}
