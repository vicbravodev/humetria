<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use InvalidArgumentException;

/**
 * An exact fraction: a rate (0.0735), a factor (383/365) or a count (15 days). It never becomes a float; money comes out
 * of it in whole cents through Money's rule.
 */
final readonly class Rate
{
    private function __construct(private BigRational $value) {}

    /**
     * valueInt / 10^scale: 7513 with scale 5 is 0.07513.
     *
     * @param  int<0, max>  $scale
     */
    public static function scaled(int $valueInt, int $scale): self
    {
        return new self(BigRational::ofFraction($valueInt, BigInteger::ten()->power($scale)));
    }

    public static function fraction(int $numerator, int $denominator): self
    {
        return new self(BigRational::ofFraction($numerator, $denominator));
    }

    public static function one(): self
    {
        return new self(BigRational::one());
    }

    public function value(): BigRational
    {
        return $this->value;
    }

    public function plus(self $other): self
    {
        return new self($this->value->plus($other->value));
    }

    public function times(self $other): self
    {
        return new self($this->value->multipliedBy($other->value));
    }

    /** -1, 0 or 1. */
    public function compareTo(self $other): int
    {
        return $this->value->compareTo($other->value);
    }

    /** This rate of an amount in cents, to the nearest cent (a half goes up). */
    public function of(int $cents): int
    {
        return Money::round($this->value->multipliedBy($cents));
    }

    /** This rate of an amount in cents, dropping the fraction of a cent (the SBC's rule). */
    public function truncated(int $cents): int
    {
        return Money::truncate($this->value->multipliedBy($cents));
    }

    /** The value as whole cents (for a money parameter); a fraction is refused. */
    public function cents(): int
    {
        try {
            return $this->value->toInt();
        } catch (RoundingNecessaryException $exception) {
            throw new InvalidArgumentException('This rate is not a whole number of cents.', previous: $exception);
        }
    }
}
