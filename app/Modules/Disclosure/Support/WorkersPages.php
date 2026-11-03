<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the public page of the workers receives (criterio C-55). Only what the company decided everybody may read: the names of
 * its centers and, per center, what it published. Never a person, an area, an email, the owner or an answer; this class
 * is the one place that decides it, and the tests walk the props to prove it.
 */
final readonly class WorkersPages
{
    public function __construct(private WorkCenters $workCenters, private PolicyStanding $policies) {}

    /**
     * The centers in use, by name, ten at a time, narrowed by a search done on the server.
     *
     * @return array{centers: LengthAwarePaginator<int, array{id: string, name: string}>, filters: array{q: string}}
     */
    public function centers(string $search): array
    {
        $perPage = config()->integer('disclosure.per_page');
        $centers = $search === '' ? null : $this->workCenters->search($search, 50);
        $page = $centers === null
            ? $this->workCenters->activePage($perPage)
            : new LengthAwarePaginator(array_slice($centers, ((LengthAwarePaginator::resolveCurrentPage()) - 1) * $perPage, $perPage), count($centers), $perPage, LengthAwarePaginator::resolveCurrentPage(), ['path' => LengthAwarePaginator::resolveCurrentPath()]);

        return [
            'centers' => $page->withQueryString()->through(fn (WorkCenterSummary $center): array => ['id' => $center->id, 'name' => $center->name]),
            'filters' => ['q' => $search],
        ];
    }

    /**
     * What one center publishes: the policy in force there.
     *
     * @return array{center: array{id: string, name: string}, others: bool, policy: array<string, mixed>|null, blocks: array<string, array{text: string, publishedOn: string}|null>}
     */
    public function center(WorkCenterSummary $center): array
    {
        $fact = $this->policies->inForce([$center->id])[$center->id] ?? null;
        $document = $fact === null ? null : Document::query()->find($fact->documentId);

        return [
            'center' => ['id' => $center->id, 'name' => $center->name],
            // Whether there is another center to choose, for the way back to the list.
            'others' => $this->workCenters->activePage(2)->total() > 1,
            'policy' => $fact === null || $document === null ? null : [
                'version' => $fact->version,
                'publishedOn' => Days::moment($fact->publishedAt),
                'sections' => PolicyText::sections($document->content, $this->workCenters->profile($center->id)->legalName ?? ''),
            ],
            'blocks' => $this->blocks($center),
        ];
    }

    /**
     * The blocks of 5.7 the company wrote (b, c, d and f), each with the text in force in the center and the day it was
     * published, or null while there is none. What other tools provide is joined here, never in place of it.
     *
     * @return array<string, array{text: string, publishedOn: string}|null>
     */
    private function blocks(WorkCenterSummary $center): array
    {
        $blocks = [];

        foreach (DocumentKind::cases() as $kind) {
            if (! $kind->isBlock()) {
                continue;
            }

            $fact = $this->policies->ofKind($kind, [$center->id])[$center->id] ?? null;
            $document = $fact === null ? null : Document::query()->find($fact->documentId);
            $blocks[$kind->inciso()] = $fact === null || $document === null ? null : ['text' => ContentPages::text($document), 'publishedOn' => Days::moment($fact->publishedAt)];
        }

        return $blocks;
    }
}
