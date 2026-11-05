<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Support\Calculation\Rate;
use OutOfBoundsException;

/**
 * The fiscal and legal values that rule on one date. A value is addressed by its key ("uma", "infonavit") or, when it
 * depends on the state, by "key:STATE" ("payroll_tax:JAL"). Past its last vigencia a value is carried and listed as
 * stale, so the report can say so (spec, decision 11).
 */
final readonly class FiscalSnapshot
{
    /** LSS art. 28: the SBC is capped at 25 times the UMA. */
    private const int SBC_CAP_UMAS = 25;

    /** The money parameters that follow prices. */
    private const array INDEXED_KEYS = ['uma', 'minimum_wage', 'minimum_wage_border'];

    /**
     * @param  array<string, Rate>  $values
     * @param  list<string>  $staleKeys  sorted
     */
    public function __construct(private array $values, private array $staleKeys) {}

    /** @throws OutOfBoundsException when no vigencia covers the date yet or the key does not exist */
    public function rate(string $key): Rate
    {
        return $this->values[$key] ?? throw new OutOfBoundsException("No fiscal parameter [{$key}] is valid on that date.");
    }

    /** A money parameter, in cents. */
    public function cents(string $key): int
    {
        return $this->rate($key)->integer();
    }

    public function uma(): int
    {
        return $this->cents('uma');
    }

    public function minimumWage(): int
    {
        return $this->cents('minimum_wage');
    }

    public function sbcCap(): int
    {
        return $this->uma() * self::SBC_CAP_UMAS;
    }

    /** The payroll tax rate of a state (ISO 3166-2:MX code), or null when it is not modelled as a flat rate (Sinaloa). */
    public function payrollTax(string $state): ?Rate
    {
        return $this->values["payroll_tax:{$state}"] ?? null;
    }

    /** LFT art. 76: vacation days by completed years of service; 0 with less than one year. */
    public function legalVacationDays(int $years): int
    {
        for ($from = $years; $from >= 1; $from--) {
            if (isset($this->values["vacation_days.{$from}"])) {
                return $this->values["vacation_days.{$from}"]->integer();
            }
        }

        return 0;
    }

    /** The employer's cesantia and vejez rate for a daily SBC, by range and year. */
    public function severanceOldAgeRate(int $sbcCents): Rate
    {
        return $this->rate('severance_old_age.'.$this->severanceBracket($sbcCents));
    }

    /**
     * The same values with the UMA and the minimum wages that were carried past their vigencia grown by a factor, because a
     * carried value is a year old or more. A value the platform knows for the date, and every rate, stays as it is.
     */
    public function indexed(Rate $factor): self
    {
        $values = $this->values;

        foreach (self::INDEXED_KEYS as $key) {
            if (in_array($key, $this->staleKeys, true) && isset($values[$key])) {
                $values[$key] = Rate::scaled($factor->of($values[$key]->integer()), 0);
            }
        }

        return new self($values, $this->staleKeys);
    }

    public function isStale(): bool
    {
        return $this->staleKeys !== [];
    }

    /** @return list<string> */
    public function staleKeys(): array
    {
        return $this->staleKeys;
    }

    /**
     * The range of the cesantia y vejez table (1 = "1.0 SM" ... last = "4.01 UMA en adelante"). The ranges are data: the
     * upper limit of range n, in UMA, is `severance_old_age_limit.n` and the last range has none.
     *
     * DECISION PENDING with the user (criterios.md section 7.1): this applies the literal text. Range 1 is a SBC at or
     * below the minimum wage; any other SBC goes to the first range whose upper limit it does not exceed (limits
     * inclusive). With the 2026 minimum wage (2.69 UMA) ranges 2 to 4 are therefore unreachable. If the IMSS criterion
     * turns out to be different, this is the only place to change.
     */
    private function severanceBracket(int $sbcCents): int
    {
        if ($sbcCents <= $this->minimumWage()) {
            return 1;
        }

        $sbc = Rate::scaled($sbcCents, 0);
        $uma = Rate::scaled($this->uma(), 0);
        $bracket = 2;

        while (isset($this->values["severance_old_age_limit.{$bracket}"])) {
            if ($sbc->compareTo($this->values["severance_old_age_limit.{$bracket}"]->times($uma)) <= 0) {
                return $bracket;
            }

            $bracket++;
        }

        return $bracket;
    }
}
