<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Gate;

/**
 * The NOM-035 route of one work center (board V2-Ruta-N035): where it stands in its six stages, what each asks for in plain
 * words, what cannot wait before it (for whoever may act on it), its last evaluation and until when its two-year cycle runs;
 * and «Si hoy llegara una inspección» from the same requirements (V3-Inspeccion). NOM-035 spans several modules, so it is put
 * together here from their contracts; NOM-037 and Ley Silla have their pages in their modules. Counts only: no name, no answer.
 */
final readonly class RoutePage
{
    public function __construct(
        private CampaignRecords $campaigns,
        private Nom035Route $nom035,
        private Nom035Rows $rows,
        private PanelUrgencies $urgencies,
        private Headcount $headcount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings): array
    {
        [$facts, $evaluated, $rows, $urgencies] = $this->read($tenantSlug, $center, $seesScreenings);
        $route = Nom035Stages::from($facts);

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'people' => $this->headcount->byWorkCenter()[$center->id] ?? 0,
            'evaluation' => $evaluated === null ? null : ['closedOn' => $evaluated->closedOn, ...Nom035Route::answered($evaluated)],
            'route' => ['applies' => $route->applies, 'done' => $route->done, 'status' => $route->status, 'answered' => $route->answered],
            'rows' => $rows,
            'urgencies' => $urgencies,
            'until' => $facts->until,
            'inspectionHref' => route('tenant.nom035.inspection', [$tenantSlug, $center->id]),
            'dossierHref' => Gate::allows('manage-disclosure') ? route('tenant.nom035.dossier', [$tenantSlug, $center->id]) : null,
        ];
    }

    /**
     * «Si hoy llegara una inspección» (board V3-Inspeccion): the requirements of the route split into what the center can
     * show and what is missing, in the order of the route; the dossier for whoever may read it.
     *
     * @return array<string, mixed>
     */
    public function inspection(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings): array
    {
        $rows = array_map(fn (array $row): array => array_diff_key($row, ['stage' => true]), $this->read($tenantSlug, $center, $seesScreenings)[2]);
        $applicable = array_values(array_filter($rows, fn (array $row): bool => $row['state'] !== 'not_applicable'));

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'applicable' => count($applicable),
            'ready' => array_values(array_filter($applicable, fn (array $row): bool => $row['state'] === 'done')),
            'missing' => array_values(array_filter($applicable, fn (array $row): bool => $row['state'] !== 'done')),
            'dossierHref' => Gate::allows('manage-disclosure') ? route('tenant.nom035.dossier', [$tenantSlug, $center->id]) : null,
        ];
    }

    /**
     * The requirements of the route of a center, one per stage, as its route and its inspection read them.
     *
     * @return list<array{numeral: string, title: string, detail: string|null, state: string, stage: int, href: string|null, action: string|null}>
     */
    public function rows(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings): array
    {
        return $this->read($tenantSlug, $center, $seesScreenings)[2];
    }

    /**
     * @return array{0: Nom035Facts, 1: CampaignRecord|null, 2: list<array{numeral: string, title: string, detail: string|null, state: string, stage: int, href: string|null, action: string|null}>, 3: list<array{norm: string, title: string, detail: string, action: string, href: string}>}
     */
    private function read(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings): array
    {
        $current = $this->campaigns->current();
        $latest = $this->campaigns->latestClosedEvaluations([$center->id]);
        $evaluations = [...array_values($latest), ...array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->workCenterId === $center->id && $campaign->launchedOn !== null)];
        $toChannel = $seesScreenings && $evaluations !== [] ? $this->nom035->toChannel(array_values(array_unique(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $evaluations)))) : [];
        $facts = $this->nom035->facts([$center->id], $current, $latest, $toChannel)[$center->id];
        $evaluated = $latest[$center->id] ?? null;
        $waiting = array_find($evaluations, fn (CampaignRecord $campaign): bool => ($toChannel[$campaign->id] ?? 0) > 0);

        return [
            $facts,
            $evaluated,
            $this->rows->for($tenantSlug, $center->id, $facts, $evaluated, $waiting?->id),
            $this->urgencies->screenings($toChannel, $evaluations, [$center->id => $center->name], $tenantSlug),
        ];
    }
}
