<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A block of questions under one instruction. With a gate, its items are asked only after a "yes".
 */
final readonly class SectionDefinition
{
    /**
     * @param  list<ItemDefinition>  $items
     */
    public function __construct(
        public ?string $title,
        public string $instruction,
        public array $items,
        public ?GateDefinition $gate = null,
    ) {}
}
