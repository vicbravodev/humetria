<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;
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
    public function __construct(private WorkCenters $workCenters, private PolicyStanding $policies, private ResultsStanding $results, private ProgramMeasureFeed $program, private ComplaintChannelFeed $channel) {}

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
     * @return array{center: array{id: string, name: string}, others: bool, policy: array<string, mixed>|null, blocks: array<string, array{text: string, sections: list<array{heading: string|null, text: string}>, publishedOn: string}|null>, measures: array<string, list<array<string, mixed>>>, channel: array<string, mixed>|null, results: array<string, mixed>|null}
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
            'measures' => $this->measures($center),
            'channel' => $this->channelOf(),
            'results' => $this->resultsOf($center),
        ];
    }

    /**
     * The blocks of 5.7 the company wrote (b, c, d and f), each with the text in force in the center and the day it was
     * published, or null while there is none. What other tools provide is joined here, never in place of it.
     *
     * @return array<string, array{text: string, sections: list<array{heading: string|null, text: string}>, publishedOn: string}|null>
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
            $blocks[$kind->inciso()] = $fact === null || $document === null ? null : ['text' => ContentPages::text($document), 'sections' => ContentPages::sections($document), 'publishedOn' => Days::moment($fact->publishedAt)];
        }

        return $blocks;
    }

    /**
     * The results block (5.7 e, 7.8; criterio C-54): null where 7.8 does not apply (a center of up to 15 people); else its title
     * and, once RH published them, the counts of the latest published evaluation, of the whole center, as Results gave them
     * with the minimum applied. Below it the figures are not sent, nor how many answered. Never an area.
     *
     * @return array<string, mixed>|null
     */
    private function resultsOf(WorkCenterSummary $center): ?array
    {
        $fact = $this->results->forCenters([$center->id])[$center->id] ?? null;

        if ($fact === null || ! $fact->applies) {
            return null;
        }

        $breakdown = $fact->shownBreakdown;
        $published = null;

        if ($fact->shown !== null && $breakdown !== null && in_array($breakdown->state, [RiskBreakdown::AVAILABLE, RiskBreakdown::BELOW_MINIMUM], true)) {
            $published = $breakdown->state === RiskBreakdown::BELOW_MINIMUM
                ? ['state' => 'below_minimum', 'closedOn' => $fact->shown->closedOn]
                : [
                    'state' => 'available',
                    'closedOn' => $fact->shown->closedOn,
                    'levels' => array_map(fn (RiskLevel $level): array => ['value' => $level->value, 'label' => $level->label()], RiskLevel::cases()),
                    'guide' => in_array('nom035.guide-iii', $fact->shown->instruments, true) ? 'iii' : 'ii',
                    'answered' => $breakdown->answered,
                    'themes' => ResultThemes::of($breakdown),
                    'final' => $breakdown->final,
                    'categories' => array_map(fn (RiskGroup $group): array => ['key' => $group->key, 'label' => $group->label, 'levels' => $group->levels], $breakdown->categories),
                    'domains' => array_map(fn (RiskGroup $group): array => ['key' => $group->key, 'label' => $group->label, 'category' => $group->category, 'levels' => $group->levels], $breakdown->domains),
                ];
        }

        return [
            'title' => $fact->guide === 'iii' ? 'Resultados de la identificación y análisis de los factores de riesgo psicosocial y de la evaluación del entorno organizacional' : 'Resultados de la identificación y análisis de los factores de riesgo psicosocial',
            'published' => $published,
        ];
    }

    /**
     * The measures of the Programa of the evaluation shown here, by inciso (b and c), when RH chose to show them with the results.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function measures(WorkCenterSummary $center): array
    {
        $fact = $this->results->forCenters([$center->id])[$center->id] ?? null;
        $measures = ['b' => [], 'c' => []];

        if ($fact === null || ! $fact->showMeasures || $fact->shown === null) {
            return $measures;
        }

        foreach ($this->program->forCampaign($fact->shown->id) as $measure) {
            $measures[$measure->inciso][] = ['title' => $measure->title, 'level' => $measure->level, 'status' => $measure->status, 'statusLabel' => $measure->statusLabel, 'dueOn' => $measure->dueOn];
        }

        return $measures;
    }

    /**
     * The channel for complaints as the channel says it, or null while it takes nothing.
     *
     * @return array{url: string, anonymousAllowed: bool, categories: list<string>}|null
     */
    private function channelOf(): ?array
    {
        $channel = $this->channel->current();

        return $channel === null ? null : ['url' => $channel->url, 'anonymousAllowed' => $channel->anonymousAllowed, 'categories' => $channel->categories];
    }
}
