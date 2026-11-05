<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Money is whole cents (int) everywhere; a fraction of a cent only exists as an exact BigRational until it is settled
 * here. The rule (decision of the plan, 2026-11-03): the SBC is truncated to the cent, everything else is rounded to the
 * nearest cent with halves going up (away from zero for a negative amount).
 */
final class Money
{
    /** Whole cents of an amount in pesos ("524.65"); a fraction of a cent is refused, not rounded. */
    public static function cents(int|string $pesos): int
    {
        try {
            return BigDecimal::of($pesos)->multipliedBy(100)->toScale(0)->toInt();
        } catch (RoundingNecessaryException $exception) {
            throw new InvalidArgumentException("{$pesos} has a fraction of a cent.", previous: $exception);
        }
    }

    /** Drops the fraction of a cent (toward zero). */
    public static function truncate(BigRational $cents): int
    {
        return $cents->toScale(0, RoundingMode::Down)->toInt();
    }

    /** Nearest cent; a half goes up. */
    public static function round(BigRational $cents): int
    {
        return $cents->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /** "$2,933" for 293,275 cents: whole pesos, a half going up, for the headline numbers. */
    public static function pesos(int $cents): string
    {
        $pesos = self::round(BigRational::ofFraction($cents, 100));

        return ($pesos < 0 ? '-' : '').'$'.number_format(abs($pesos));
    }

    /** "$2,932.75"; a negative amount is "-$2,932.75". */
    public static function format(int $cents): string
    {
        $absolute = abs($cents);

        return ($cents < 0 ? '-' : '').'$'.number_format(intdiv($absolute, 100)).'.'.sprintf('%02d', $absolute % 100);
    }
}
