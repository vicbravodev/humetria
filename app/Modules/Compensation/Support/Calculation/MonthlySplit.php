<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use InvalidArgumentException;

/**
 * Spreads a cost over the months it is accrued in. Each month gives its annualised amount (what that month would cost over a
 * whole year); a month's share is a twelfth of it. The year is the sum of those twelfths settled once to the cent (a half
 * goes up), and the months are the whole cents of each twelfth plus the cents that are left, given to the largest
 * remainders (the earliest month on a tie). So the months add up to the year exactly and each stays within a cent of its
 * true share.
 */
final class MonthlySplit
{
    /**
     * @param  list<int>  $annualised  one non-negative amount per month, in cents
     * @return list<int>
     */
    public static function of(array $annualised): array
    {
        $parts = count($annualised);

        if ($parts === 0) {
            return [];
        }

        $months = [];
        $remainders = [];

        foreach ($annualised as $index => $amount) {
            if ($amount < 0) {
                throw new InvalidArgumentException('A cost cannot be negative.');
            }

            $months[$index] = intdiv($amount, $parts);
            $remainders[$index] = $amount % $parts;
        }

        $extra = intdiv(2 * array_sum($remainders) + $parts, 2 * $parts);
        $order = array_keys($remainders);
        usort($order, fn (int $a, int $b): int => $remainders[$b] <=> $remainders[$a] ?: $a <=> $b);

        foreach (array_slice($order, 0, $extra) as $index) {
            $months[$index]++;
        }

        return $months;
    }
}
