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
     * @param  int  $minutes  About how long a person takes to answer it, to tell them before they start.
     */
    public function __construct(
        public string $key,
        public string $version,
        public string $title,
        public string $shortTitle,
        public bool $identified,
        public array $options,
        public array $sections,
        public int $minutes,
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
