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

    /**
     * Accepts only a complete questionnaire, coherent with its yes/no gates. Answers are keyed by item number ("1"…)
     * and gate key (serves_clients, supervises).
     *
     * @param  array<array-key, mixed>  $answers
     *
     * @throws InvalidArgumentException with a message for the person answering.
     */
    public function validate(string $key, array $answers): void;
}
