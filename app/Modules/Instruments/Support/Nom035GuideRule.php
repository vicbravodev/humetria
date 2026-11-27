<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\Nom035\GuideOne;
use App\Modules\Instruments\Support\Nom035\GuideThree;
use App\Modules\Instruments\Support\Nom035\GuideTwo;

/** The norm's rule alone (num. 4.4 and 2): the work center never changes it. */
final class Nom035GuideRule implements Nom035Guides
{
    public function for(int $workers, ?string $workCenterId = null): array
    {
        return match (true) {
            $workers <= 0 => [],
            $workers <= 15 => ['I'],
            $workers <= 50 => ['I', 'II'],
            default => ['I', 'III'],
        };
    }

    public function instruments(int $workers, ?string $workCenterId = null): array
    {
        $keys = ['I' => GuideOne::KEY, 'II' => GuideTwo::KEY, 'III' => GuideThree::KEY];

        return array_map(fn (string $guide): string => $keys[$guide], $this->for($workers));
    }
}
