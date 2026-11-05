<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\PageLinkStatus;
use App\Modules\Disclosure\Enums\RequirementState;
use App\Modules\Disclosure\Models\PageLink;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The board of the disclosure: every work center in use with the requirements of 5.1, 5.7 and 7.8 and where each stands, and
 * how many centers are in each overall state. The state of a center is its worst row. It reads what is recorded in bulk
 * (a fixed number of queries, whatever the number of centers) and lists the centers a page at a time, by name. Counts and
 * states only: nothing here names a person.
 */
final readonly class Board
{
    public function __construct(private WorkCenters $workCenters, private PolicyStanding $policies, private DeliveryStanding $delivery, private ResultsStanding $results, private ProgramMeasureFeed $program, private ComplaintChannelFeed $channel, private Requirements $requirements, private Matrix $matrix) {}

    /**
     * The board as the matrix of what the people must know (board V4-Difusion): every center with its requirements and its
     * cells, a page at a time, and the rows with how many are ready and the next step.
     *
     * @return array{summary: array{met: int, notice: int, missing: int}, centers: LengthAwarePaginator<int, array<string, mixed>>, items: list<array<string, mixed>>, progress: array{ready: int, total: int, minutes: int}, next: array<string, mixed>|null, reach: array{page: bool, confirmed: int, people: int}}
     */
    public function page(): array
    {
        $centers = array_values(array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));
        $facts = $this->facts($centers);
        $rows = [];
        $states = [];
        $cells = [];
        $byId = [];

        foreach ($centers as $center) {
            $requirements = $this->requirements->for($facts[$center->id]);
            $state = $this->overall($requirements);
            $states[] = $state->value;
            $cells[$center->id] = Matrix::cells($facts[$center->id]);
            $byId[$center->id] = $center;
            $rows[] = $this->row($center, $requirements, $state, $cells[$center->id]);
        }

        $counts = array_count_values($states);
        $summary = ['met' => $counts['met'] ?? 0, 'notice' => $counts['notice'] ?? 0, 'missing' => $counts['missing'] ?? 0];

        $perPage = config()->integer('disclosure.per_page');
        $current = LengthAwarePaginator::resolveCurrentPage();

        return [
            'summary' => $summary,
            'centers' => (new LengthAwarePaginator(array_slice($rows, ($current - 1) * $perPage, $perPage), count($rows), $perPage, $current, ['path' => LengthAwarePaginator::resolveCurrentPath()]))->withQueryString(),
            ...$this->matrix->of($cells, $facts, $byId),
        ];
    }

    /**
     * What is recorded about each of these centers that the board judges, read in bulk (a fixed number of queries per kind of
     * fact, whatever the number of centers): the single source of the board and of a center's dossier.
     *
     * @param  list<WorkCenterSummary>  $centers
     * @return array<string, CenterFacts> by work center id
     */
    public function facts(array $centers): array
    {
        $ids = array_map(fn (WorkCenterSummary $center): string => $center->id, $centers);
        $policies = $this->policies->inForce($ids);
        $recorded = $this->delivery->recordedItems($ids);
        $results = $this->results->forCenters($ids);
        $contents = $this->contents($ids, $results);
        $acknowledgments = $this->delivery->acknowledgments($policies);
        $link = PageLink::query()->first();
        $active = $link !== null && $link->status() === PageLinkStatus::Active;
        $expires = $link !== null && $active && $link->expires_at !== null && $link->expires_at->lessThanOrEqualTo(now()->addDays(config()->integer('disclosure.link_expiry_warning_days'))) ? Days::moment($link->expires_at) : null;
        $facts = [];

        foreach ($ids as $id) {
            $facts[$id] = new CenterFacts($policies[$id] ?? null, $acknowledgments[$id] ?? null, $recorded[$id] ?? [], $active, $expires, $contents[$id] ?? [], $results[$id] ?? null);
        }

        return $facts;
    }

    /**
     * @param  list<Requirement>  $requirements
     * @param  array<string, string>  $cells  Where each thing of 5.7 stands in it (Matrix::cells).
     * @return array<string, mixed>
     */
    private function row(WorkCenterSummary $center, array $requirements, RequirementState $state, array $cells): array
    {
        return [
            'id' => $center->id,
            'name' => $center->name,
            'state' => ['value' => $state->value, 'label' => $state->label()],
            'requirements' => array_map(fn (Requirement $requirement): array => $requirement->toArray(), $requirements),
            'cells' => $cells,
        ];
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    private function overall(array $requirements): RequirementState
    {
        $states = array_map(fn (Requirement $requirement): RequirementState => $requirement->state, $requirements);

        return match (true) {
            in_array(RequirementState::Missing, $states, true) => RequirementState::Missing,
            in_array(RequirementState::Notice, $states, true) => RequirementState::Notice,
            default => RequirementState::Met,
        };
    }

    /**
     * The incisos (b, c, d, f) with something published, by work center: a text the company wrote, the measures of the Programa
     * it chose to show and the channel for complaints. What other tools give is joined to the text, never in place of it.
     *
     * @param  list<string>  $workCenterIds
     * @param  array<string, ResultsFact>  $results
     * @return array<string, list<string>>
     */
    private function contents(array $workCenterIds, array $results): array
    {
        $found = [];

        foreach (DocumentKind::cases() as $kind) {
            if ($kind->isBlock()) {
                foreach (array_keys($this->policies->ofKind($kind, $workCenterIds)) as $center) {
                    $found[$center][] = $kind->inciso();
                }
            }
        }

        $channel = $this->channel->current() !== null;

        foreach ($workCenterIds as $center) {
            $fact = $results[$center] ?? null;

            if ($fact !== null && $fact->showMeasures && $fact->shown !== null) {
                foreach ($this->program->forCampaign($fact->shown->id) as $measure) {
                    $found[$center][] = $measure->inciso;
                }
            }

            if ($channel) {
                $found[$center][] = 'd';
            }

            $found[$center] = array_values(array_unique($found[$center] ?? []));
        }

        return array_filter($found, fn (array $items): bool => $items !== []);
    }
}
