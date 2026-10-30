<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Support\Nom035\GuideOne;
use App\Modules\Instruments\Support\Nom035\GuideThree;
use App\Modules\Instruments\Support\Nom035\GuideTwo;
use InvalidArgumentException;

final class InstrumentCatalog implements Instruments
{
    public function definition(string $key): InstrumentDefinition
    {
        return match ($key) {
            GuideOne::KEY => GuideOne::definition(),
            GuideTwo::KEY => GuideTwo::definition(),
            GuideThree::KEY => GuideThree::definition(),
            default => throw new InvalidArgumentException("Unknown instrument [{$key}]."),
        };
    }
}
