<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use App\Modules\Instruments\Data\InstrumentDefinition;
use InvalidArgumentException;

/**
 * The questionnaires the platform applies, by key (nom035.guide-i, nom035.guide-ii, nom035.guide-iii).
 */
interface Instruments
{
    /**
     * @throws InvalidArgumentException for a key it does not know.
     */
    public function definition(string $key): InstrumentDefinition;
}
