<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A questionnaire as a person answers it: its sections, items and answer options. An identified instrument is answered
 * under the person's name; an anonymous one never is.
 */
final readonly class InstrumentDefinition
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<SectionDefinition>  $sections
     */
    public function __construct(
        public string $key,
        public string $version,
        public string $title,
        public bool $identified,
        public array $options,
        public array $sections,
    ) {}

    /**
     * @return list<int>
     */
    public function itemNumbers(): array
    {
        $numbers = [];

        foreach ($this->sections as $section) {
            foreach ($section->items as $item) {
                $numbers[] = $item->number;
            }
        }

        return $numbers;
    }
}
