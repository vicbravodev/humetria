<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Contracts\Nom035Guides;

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
}
