<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * The route of one standard in one work center (rediseño v2, boards V3-Movil and V3-AlDia): where it stands in its six
 * stages, what cannot wait before it (for whoever may act on it) and, for NOM-035, until when its two-year cycle runs.
 */
final readonly class RoutePage
{
    public function __construct(
        private CampaignRecords $campaigns,
        private Nom035Route $nom035,
        private TeleworkRoute $telework,
        private SeatingRoute $seating,
        private PanelUrgencies $urgencies,
    ) {}

    /**
     * @param  'nom035'|'nom037'|'seating'  $norm
     * @return array<string, mixed>
     */
    public function props(string $tenantSlug, WorkCenterSummary $center, string $norm, bool $seesScreenings, bool $reviewsNotices): array
    {
        $names = [$center->id => $center->name];
        [$route, $urgencies, $until] = match ($norm) {
            'nom035' => $this->nom035($tenantSlug, $center->id, $names, $seesScreenings),
            'nom037' => [
                $this->telework->progress([$center->id])[$center->id],
                $reviewsNotices ? array_values(array_map(
                    fn (UnreviewedAccidents $accidents): array => $this->urgencies->accident($accidents, $names, $tenantSlug),
                    array_filter($this->telework->accidents(), fn (UnreviewedAccidents $accidents): bool => $accidents->workCenterId === $center->id),
                )) : [],
                null,
            ],
            'seating' => [$this->seating->progress([$center->id])[$center->id], [], null],
        };

        return [
            'norm' => $norm,
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
