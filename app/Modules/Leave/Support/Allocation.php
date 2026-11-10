<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

/**
 * Where the days of a request come from, and how many of them the person does not have.
 */
final readonly class Allocation
{
    /**
     * @param  list<AllocationLine>  $lines
     */
    public function __construct(public array $lines, public int $shortBy) {}

    public function covered(): bool
    {
        return $this->shortBy === 0;
    }
}
