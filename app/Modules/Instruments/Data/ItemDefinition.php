<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A question of an instrument, with the number and the words the norm gives it.
 */
final readonly class ItemDefinition
{
    public function __construct(public int $number, public string $text) {}
}
