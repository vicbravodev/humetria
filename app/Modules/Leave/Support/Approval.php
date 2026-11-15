<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

/**
 * What approving vacations does with the days of the periods: where they come from and how many the periods would still
 * hold afterwards. The approval and the queue read it from here, so what RH is told fits is what approving charges.
 */
final readonly class Approval
{
    public function __construct(public Allocation $allocation, public int $leftAfter) {}

    /**
     * @param  list<PeriodBalance>  $periods  all of the person's periods
     */
    public static function assess(array $periods, int $days): self
    {
        $left = array_sum(array_map(fn (PeriodBalance $period): int => $period->left(), $periods));

        return new self(Allocator::allocate($periods, $days), $left - $days);
    }

    /** Whether the periods cover every day, with none given in advance. */
    public function fits(): bool
    {
        return $this->allocation->covered();
    }
}
