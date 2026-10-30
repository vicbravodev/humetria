<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\SectionDefinition;
use App\Modules\Instruments\Exceptions\InvalidAnswers;
use App\Modules\Instruments\Support\Nom035\GuideOne;

/**
 * Accepts only a complete questionnaire, with nothing it does not ask (II.2 c, III.2 c: "respondidos completamente"):
 * every item its sections ask, every gate answered yes or no, a gated block whole after a "yes" and absent after a
 * "no", and Guía I ended after a section I answered all "no" (GR.I a).
 */
final class AnswerValidator
{
    private const array GATE_VALUES = ['yes', 'no'];

    /**
     * @param  array<array-key, mixed>  $answers
     *
     * @throws InvalidAnswers
     */
    public function validate(InstrumentDefinition $definition, array $answers): void
    {
        $expected = $this->expectedValues($definition, $answers);

        foreach (array_keys($expected) as $key) {
            if (! array_key_exists($key, $answers)) {
                throw InvalidAnswers::incomplete();
            }
        }

        foreach ($answers as $key => $value) {
            if (! in_array($value, $expected[$key] ?? [], true)) {
                throw InvalidAnswers::unexpected();
            }
        }
    }

    /**
     * What each asked key may be answered with, given the gates as answered. Item numbers are int keys, as PHP stores
     * "1", "2"…; gates are string keys.
     *
     * @param  array<array-key, mixed>  $answers
     * @return array<array-key, list<string>>
     */
    private function expectedValues(InstrumentDefinition $definition, array $answers): array
    {
        $options = array_column($definition->options, 'value');
        $expected = [];

        foreach ($definition->sections as $index => $section) {
            if ($index > 0 && $definition->key === GuideOne::KEY && ! GuideOne::continuesAfterSectionOne($answers)) {
                break;
            }

            if ($section->gate !== null) {
                $expected[$section->gate->key] = self::GATE_VALUES;

                if (($answers[$section->gate->key] ?? null) !== 'yes') {
                    continue;
                }
            }

            // `+`, not a spread: a spread renumbers the numeric item keys.
            $expected += $this->itemValues($section, $options);
        }

        return $expected;
    }

    /**
     * @param  list<string>  $options
     * @return array<int, list<string>>
     */
    private function itemValues(SectionDefinition $section, array $options): array
    {
        $values = [];

        foreach ($section->items as $item) {
            $values[$item->number] = $options;
        }

        return $values;
    }
}
