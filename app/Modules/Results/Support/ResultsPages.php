<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Tenancy\Contracts\WorkCenters;

/**
 * What the results of a campaign show. Guía II or III results exist only once the evaluation is closed: while it runs,
 * HR sees who answered, and results that moved with each answer would tell what the last person said. Every result
 * goes through RiskDistribution. The Guía I is shown only to whoever may channel people (`$withScreenings`).
 */
final readonly class ResultsPages
{
    public function __construct(
        private ResponseReader $responses,
        private RiskDistribution $distribution,
        private Instruments $instruments,
        private WorkCenters $workCenters,
        private Areas $areas,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function show(CampaignRecord $campaign, ?string $areaId, bool $withScreenings): array
    {
        $guide = self::riskGuide($campaign);
        $results = $guide !== null && $campaign->closed ? $this->results($campaign, $guide, $areaId) : null;

        return [
            'campaign' => [
                'id' => $campaign->id,
                'workCenter' => $this->workCenters->profile($campaign->workCenterId)->name ?? '',
                'kindLabel' => $campaign->kindLabel,
                'closed' => $campaign->closed,
                'launchedOn' => $campaign->launchedOn,
                'closedOn' => $campaign->closedOn,
                'workers' => $campaign->workers,
                'sampleSize' => $campaign->sampleSize,
            ],
            'guide' => $guide === null ? null : ['key' => $guide, 'title' => $this->instruments->definition($guide)->shortTitle],
            'participation' => $guide === null ? null : ['invited' => $campaign->invited[$guide] ?? 0, 'answered' => $campaign->answered[$guide] ?? 0],
            'results' => $results['results'] ?? null,
            'areas' => $results['areas'] ?? null,
            'items' => $results['items'] ?? null,
            'filters' => ['area' => $results['area'] ?? null],
            'levels' => RiskLevels::catalog(),
            'trauma' => $withScreenings ? $this->responses->screeningCounts($campaign->id) : null,
        ];
    }

    /**
     * @return array{results: array<string, mixed>, areas: array{visible: list<array<string, mixed>>, hidden: int}, items: list<array<string, mixed>>, area: array{value: string, label: string}|null}
     */
    private function results(CampaignRecord $campaign, string $guide, ?string $areaId): array
    {
        $records = $this->responses->anonymous($campaign->id, $guide);
        $areas = $this->distribution->areas($guide, $records);
        $names = $this->areas->names(array_column($areas['visible'], 'areaId'));
        $filtered = $areaId !== null && isset($names[$areaId]) ? $areaId : null;

        return [
            'results' => $this->distribution->of($guide, $records, $filtered),
            'areas' => [
                'visible' => array_map(fn (array $area): array => ['id' => $area['areaId'], 'name' => $names[$area['areaId']] ?? '', 'answered' => $area['answered'], 'final' => $area['final']], $areas['visible']),
                'hidden' => $areas['hidden'],
            ],
            'items' => $this->distribution->items($guide, $records),
            'area' => $filtered === null ? null : ['value' => $filtered, 'label' => $names[$filtered]],
        ];
    }

    /** The guide scored by risk level a campaign applies (Guía II or III), if any. */
    public static function riskGuide(CampaignRecord $campaign): ?string
    {
        return array_find($campaign->instruments, fn (string $key): bool => $key !== 'nom035.guide-i');
    }
}
