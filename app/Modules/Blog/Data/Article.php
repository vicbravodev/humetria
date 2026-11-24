<?php

declare(strict_types=1);

namespace App\Modules\Blog\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** One blog article, read from its Markdown file. `reviewedAt` null is a draft: nobody has signed it off yet. */
final readonly class Article
{
    /**
     * @param  list<string>  $sources  official sources the article cites (https URLs of gob.mx)
     * @param  list<array{id: string, text: string}>  $headings  the h2 of the body, for the table of contents
     * @param  list<array{type: 'html', html: string}|array{type: 'diagram', name: string}>  $body  `html` without the diagrams, cut where each one goes
     * @param  list<string>  $diagrams  the names of the diagrams the body asks for (components/blog/diagrams)
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public string $tool,
        public string $toolName,
        public string $family,
        public string $toolRoute,
        public CarbonImmutable $publishedAt,
        public CarbonImmutable $updatedAt,
        public ?string $reviewedBy,
        public ?CarbonImmutable $reviewedAt,
        public array $sources,
        public string $html,
        public array $headings,
        public array $body,
        public array $diagrams,
        public int $readingMinutes,
    ) {}

    /** The cover shown on the page and the cards: public/images/blog/{slug}.webp, 1200x630, made by `npm run covers:blog` in video/. */
    public function cover(): string
    {
        return '/images/blog/'.$this->slug.'.webp';
    }

    /** The cover with the title on it, for Open Graph and the JSON-LD image (a JPEG: not every crawler reads WebP). */
    public function ogImage(): string
    {
        return '/images/blog/'.$this->slug.'-og.jpg';
    }

    /** Published means reviewed by the owner and dated today or earlier. */
    public function isPublished(DateTimeInterface $now): bool
    {
        return $this->reviewedAt !== null && $this->publishedAt <= $now;
    }
}
