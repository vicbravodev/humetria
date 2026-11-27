<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Demos\Contracts\DemoSessionCenter;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\Nom035\GuideOne;
use App\Modules\Instruments\Support\Nom035\GuideTwo;

/**
 * The norm's rule, except in the demo session's work center: there a handful of people in the room answers Guías I and II
 * (spec 2026-11-26-demo-guiada §2.2), as a center of 16 to 50 would. Above 15 and everywhere else, the rule stands.
 */
final readonly class DemoNom035Guides implements Nom035Guides
{
    public function __construct(private Nom035GuideRule $rule, private DemoSessionCenter $sessionCenter) {}

    public function for(int $workers, ?string $workCenterId = null): array
    {
        return $this->bent($workers, $workCenterId) ? ['I', 'II'] : $this->rule->for($workers);
    }

    public function instruments(int $workers, ?string $workCenterId = null): array
    {
        return $this->bent($workers, $workCenterId) ? [GuideOne::KEY, GuideTwo::KEY] : $this->rule->instruments($workers);
    }

    /** The rule asks this center for Guía I alone, and it is the session's room. */
    private function bent(int $workers, ?string $workCenterId): bool
    {
        return $this->rule->for($workers) === ['I'] && $workCenterId !== null && $this->sessionCenter->is($workCenterId);
    }
}
