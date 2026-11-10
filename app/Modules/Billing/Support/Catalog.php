<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use InvalidArgumentException;

/**
 * One version of the catalog in config/billing.php, read with types. Nothing else knows where a price comes from.
 */
final readonly class Catalog
{
    private function __construct(private string $version) {}

    /**
     * @throws InvalidArgumentException when the config has no such version.
     */
    public static function version(string $version): self
    {
        if (! array_key_exists($version, config()->array('billing.versions'))) {
            throw new InvalidArgumentException("No hay catálogo [{$version}].");
        }

        return new self($version);
    }

    /** The version a new subscription is quoted with. */
    public static function current(): self
    {
        return self::version(config()->string('billing.current_version'));
    }

    public function name(): string
    {
        return $this->version;
    }

    public function currency(): string
    {
        return config()->string('billing.currency');
    }

    public function effectiveOn(): string
    {
        return $this->string('effective_on');
    }

    public function vatPercent(): int
    {
        return $this->integer('vat_percent');
    }

    public function extraCenterCents(): int
    {
        return $this->integer('extra_center_cents');
    }

    public function rateCents(Plan $plan): int
    {
        return $this->integer("plans.{$plan->value}.rate_cents");
    }

    public function minimumCents(Plan $plan): int
    {
        return $this->integer("plans.{$plan->value}.minimum_cents");
    }

    public function includedCenters(Plan $plan): int
    {
        return $this->integer("plans.{$plan->value}.included_centers");
    }

    /**
     * The modules the plan enables.
     *
     * @return list<string>
     */
    public function modules(Plan $plan): array
    {
        return $this->strings("plans.{$plan->value}.modules");
    }

    public function quota(Plan $plan, string $resource): int
    {
        return $this->integer("plans.{$plan->value}.quotas.{$resource}");
    }

    /** Beyond this many people the profile has no table: it needs a quotation. */
    public function quotationAbove(Profile $profile): int
    {
        return $this->integer("quotation_above.{$profile->value}");
    }

    /**
     * The compliance bands of the channel, the smallest first: up to this many people, this many cents.
     *
     * @return array<int, int>
     */
    public function complianceBands(): array
    {
        $bands = [];

        foreach (config()->array("billing.versions.{$this->version}.channel_compliance_bands") as $upTo => $cents) {
            if (is_int($upTo) && is_int($cents)) {
                $bands[$upTo] = $cents;
            }
        }

        ksort($bands);

        return $bands;
    }

    /** The share taken off the Integral rate of a consultant who has enough companies with it. */
    public function integralDiscountPercent(): int
    {
        return $this->integer('channel_integral.discount_percent');
    }

    /** How many companies with paid Integral a consultant needs for that discount. */
    public function integralMinimumCompanies(): int
    {
        return $this->integer('channel_integral.minimum_companies');
    }

    private function integer(string $key): int
    {
        return config()->integer("billing.versions.{$this->version}.{$key}");
    }

    private function string(string $key): string
    {
        return config()->string("billing.versions.{$this->version}.{$key}");
    }

    /**
     * @return list<string>
     */
    private function strings(string $key): array
    {
        return array_values(array_filter(config()->array("billing.versions.{$this->version}.{$key}"), is_string(...)));
    }
}
