<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Organization\Contracts\Headcount;
use App\Modules\Seating\Contracts\SeatingApplicability;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * What the «¿Te aplica?» pages send to the browser (rediseño v2, decisión A2): the people registered in a center and the
 * NOM-035 guides they mean (num. 4.4 and 2), and what it declared for Ley Silla. Counts and declarations, never a person.
 */
final readonly class ApplicabilityPages
{
    public function __construct(private Headcount $headcount, private Nom035Guides $guides, private SeatingApplicability $seating) {}

    /**
     * @param  'nom035'|'seating'  $norm  the tab it opens on
     * @return array<string, mixed>
     */
    public function center(WorkCenterSummary $center, string $norm): array
    {
        $people = $this->headcount->byWorkCenter()[$center->id] ?? 0;
        $seating = $this->seating->declarations([$center->id])[$center->id] ?? null;

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'people' => $people,
            'range' => $this->range($people),
            'norm' => $norm,
            'seating' => $seating === null ? null : ['standing' => $seating->standing, 'sector' => $seating->sector, 'declaredOn' => $seating->declaredOn],
        ];
    }

    /** Which of the three sizes of num. 4.4 the center has: what decides its NOM-035 guides. */
    private function range(int $people): string
    {
        $guides = $this->guides->for($people);

        return match (true) {
            in_array('III', $guides, true) => 'over_50',
            in_array('II', $guides, true) => '16_to_50',
            default => 'up_to_15',
        };
    }
}
