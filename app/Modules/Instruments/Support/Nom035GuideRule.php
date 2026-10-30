<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Instruments\Support\Nom035\GuideOne;
use App\Modules\Instruments\Support\Nom035\GuideThree;
use App\Modules\Instruments\Support\Nom035\GuideTwo;

final class Nom035GuideRule implements Nom035Guides
{
    public function for(int $workers): array
    {
        return match (true) {
            $workers <= 0 => [],
            $workers <= 15 => ['I'],
            $workers <= 50 => ['I', 'II'],
            default => ['I', 'III'],
        };
    }

    public function instruments(int $workers): array
    {
        $keys = ['I' => GuideOne::KEY, 'II' => GuideTwo::KEY, 'III' => GuideThree::KEY];

        return array_map(fn (string $guide): string => $keys[$guide], $this->for($workers));
    }
}
