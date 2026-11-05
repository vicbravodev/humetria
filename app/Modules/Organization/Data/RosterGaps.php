<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * How much of the roster the calculator can cost. A person is costed when they are active and have a category and
 * level, a hire date and a staff group. Whoever is not is named by the company's employee number; whoever has none is
 * only counted, so the internal id never leaves Organization.
 */
final readonly class RosterGaps
{
    /**
     * @param  int  $total  active people
     * @param  int  $costed  active people the calculator can cost
     * @param  list<string>  $missing  employee numbers of the active people who cannot be costed, sorted
     * @param  int  $missingWithoutNumber  active people who cannot be costed and have no employee number
     * @param  int  $inactive  deactivated people, who are in no count
     */
    public function __construct(
        public int $total,
        public int $costed,
        public array $missing,
        public int $missingWithoutNumber,
        public int $inactive,
    ) {}
}
