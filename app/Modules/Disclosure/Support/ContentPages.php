<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * What the page of the blocks of 5.7 receives (criterio C-52): for each of b, c, d and f, the text in force with where it was
 * published and the draft being written. The words are the company's own, meant to be read by its people.
 */
final readonly class ContentPages
{
    public function __construct(private WorkCenters $workCenters) {}

    /**
     * @return array{blocks: list<array<string, mixed>>, centersInUse: int}
     */
    public function show(): array
    {
        $blocks = [];

        foreach (array_values(array_filter(DocumentKind::cases(), fn (DocumentKind $kind): bool => $kind->isBlock())) as $kind) {
            $latest = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Published)->withCount('centers')->orderByDesc('version')->first();
            $draft = Document::query()->where('kind', $kind)->where('status', DocumentStatus::Draft)->first();

            $blocks[] = [
                'kind' => $kind->value,
                'inciso' => $kind->inciso(),
                'label' => $kind->label(),
                'note' => $kind->help(),
                'latest' => $latest === null ? null : [
                    'id' => $latest->id,
                    'version' => $latest->version,
                    'publishedOn' => $latest->published_at === null ? null : Days::moment($latest->published_at),
                    'text' => self::text($latest),
                    'centers' => $latest->centers_count ?? 0,
                ],
                'draft' => $draft === null ? null : ['id' => $draft->id, 'text' => self::text($draft)],
            ];
        }

        return ['blocks' => $blocks, 'centersInUse' => count(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived))];
    }

    /** The words of a block: its one section. */
    public static function text(Document $document): string
    {
        return array_column($document->content, 'text', 'key')['body'] ?? '';
    }
}
