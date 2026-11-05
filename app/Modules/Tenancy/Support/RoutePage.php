<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * The route of one standard in one work center (rediseño v2, boards V3-Movil and V3-AlDia): where it stands in its six
 * stages, what cannot wait before it (for whoever may act on it) and until when its two-year cycle runs. Only NOM-035 is
 * left here: NOM-037 and Ley Silla have their own pages in their modules (boards V2-Ruta-N037 and V2-Ruta-Silla).
 */
final readonly class RoutePage
{
    public function __construct(
        private CampaignRecords $campaigns,
        private Nom035Route $nom035,
        private PanelUrgencies $urgencies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(string $tenantSlug, WorkCenterSummary $center, bool $seesScreenings): array
    {
        [$route, $urgencies, $until] = $this->nom035($tenantSlug, $center->id, [$center->id => $center->name], $seesScreenings);

        return [
            'norm' => 'nom035',
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'route' => ['applies' => $route->applies, 'done' => $route->done, 'status' => $route->status, 'answered' => $route->answered],
            'urgencies' => $urgencies,
            'until' => $until,
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @return array{0: RouteProgress, 1: list<array{norm: string, title: string, detail: string, action: string, href: string}>, 2: string|null}
     */
    private function nom035(string $tenantSlug, string $centerId, array $names, bool $seesScreenings): array
    {
        $current = $this->campaigns->current();
        $latest = $this->campaigns->latestClosedEvaluations([$centerId]);
        $evaluations = [...array_values($latest), ...array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->workCenterId === $centerId && $campaign->launchedOn !== null)];
        $toChannel = $seesScreenings && $evaluations !== [] ? $this->nom035->toChannel(array_values(array_unique(array_map(fn (CampaignRecord $campaign): string => $campaign->id, $evaluations)))) : [];

        return [
            $this->nom035->progress([$centerId], $current, $latest, $toChannel)[$centerId],
            $this->urgencies->screenings($toChannel, $evaluations, $names, $tenantSlug),
            $this->campaigns->cycles([$centerId])[$centerId]->until,
        ];
    }
}
