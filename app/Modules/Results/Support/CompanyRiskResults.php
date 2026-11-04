<?php

declare(strict_types=1);

namespace App\Modules\Results\Support;

use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Organization\Contracts\Areas;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\AreaResult;
use App\Modules\Results\Data\AreaRisk;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;

final readonly class CompanyRiskResults implements RiskResults
{
    public function __construct(private ResponseReader $responses, private RiskDistribution $distribution, private Areas $areas) {}

    public function forCampaign(CampaignRecord $campaign, ?string $areaId = null): RiskBreakdown
    {
        $guide = ResultsPages::riskGuide($campaign);

        if ($guide === null) {
            return RiskBreakdown::notScored();
        }

        if (! $campaign->closed) {
            return RiskBreakdown::open();
        }

        $records = $this->responses->anonymous($campaign->id, $guide);
        $areas = $this->distribution->areas($guide, $records);
        $names = $this->areas->names(array_column($areas['visible'], 'areaId'));
        // An area that is not shown is not applied: the figures are the center's, which areaLabel tells by being null.
        $filtered = $areaId !== null && isset($names[$areaId]) ? $areaId : null;
        $result = $this->distribution->of($guide, $records, $filtered);

        if (! $result['visible']) {
            return RiskBreakdown::belowMinimum();
        }

        return new RiskBreakdown(
            RiskBreakdown::AVAILABLE,
            $result['answered'],
            $result['final'],
            array_map(self::group(...), $result['categories']),
            array_map(self::group(...), $result['domains']),
            array_map(fn (array $area): AreaResult => new AreaResult($area['areaId'], $names[$area['areaId']] ?? '', $area['answered'], $area['final']), $areas['visible']),
            $areas['hidden'],
            $filtered === null ? null : $names[$filtered],
        );
    }

    public function areaGroups(CampaignRecord $campaign): array
    {
        $guide = ResultsPages::riskGuide($campaign);

        if ($guide === null || ! $campaign->closed) {
            return [];
        }

        $records = $this->responses->anonymous($campaign->id, $guide);

        if (! $this->distribution->of($guide, $records)['visible']) {
            return [];
        }

        $visible = $this->distribution->areas($guide, $records)['visible'];
        $names = $this->areas->names(array_column($visible, 'areaId'));

        return array_map(function (array $area) use ($guide, $records, $names): AreaRisk {
            $result = $this->distribution->of($guide, $records, $area['areaId']);

            return new AreaRisk($area['areaId'], $names[$area['areaId']] ?? '', $area['answered'], $result['final'], array_map(self::group(...), $result['categories']), array_map(self::group(...), $result['domains']));
        }, $visible);
    }

    /**
     * @param  array{key: string, label: string, category: string|null, levels: list<int>}  $group
     */
    private static function group(array $group): RiskGroup
    {
        return new RiskGroup($group['key'], $group['label'], $group['category'], $group['levels']);
    }
}
