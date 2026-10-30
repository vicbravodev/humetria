<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\RiskScore;
use App\Modules\Instruments\Data\TraumaOutcome;
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

    /**
     * Whether the person of a NOM-035 Guía I needs a clinical assessment (GR.I). Validates the answers first.
     *
     * @param  array<array-key, mixed>  $answers
     *
     * @throws InvalidArgumentException when they are not a complete, coherent Guía I.
     */
    public function screenTrauma(array $answers): TraumaOutcome;

    /**
     * Scores one NOM-035 Guía II or III by domain, category and total (II.3, III.3). Validates the answers first.
     *
     * @param  array<array-key, mixed>  $answers
     *
     * @throws InvalidArgumentException for another instrument or answers that are not complete and coherent.
     */
    public function scoreRisk(string $key, array $answers): RiskScore;

    /**
     * The points one answer gives one item of a Guía II or III (Tablas 2 and 5): 0 to 4, where 3 and 4 are the side
     * that scores risk. An answer that is not on the scale gives 0.
     *
     * @throws InvalidArgumentException for an instrument not scored by risk level.
     */
    public function itemScore(string $key, int $item, string $answer): int;
}
