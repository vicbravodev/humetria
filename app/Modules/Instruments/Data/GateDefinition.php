<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

/**
 * A yes or no question that decides whether a block of items is asked: on "no", its items are skipped and score 0.
 */
final readonly class GateDefinition
{
    /**
     * @param  'serves_clients'|'supervises'  $key
     */
    public function __construct(public string $key, public string $question, public string $instruction) {}
}
