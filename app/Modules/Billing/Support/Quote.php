<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Data\QuoteLine;
use App\Modules\Billing\Data\QuoteResult;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use InvalidArgumentException;

/**
 * What a company pays for one period: the only place that computes an amount (the quoter, the preview and, later, the
 * statements all call it). Pure: the same input gives the same lines. Integer cents only; each line is rounded once, half
 * a cent up; the discount is taken from the plan line already rounded.
 */
final class Quote
{
    /**
     * @param  int  $eligibleIntegralCompanies  How many companies of the consultant pay Integral (see Billing's eligibility); it only matters to the channel Integral profile.
     * @param  int|null  $negotiatedCents  The price agreed for this company, valid today: it replaces the table (and any channel discount) and lifts the limit of people.
     * @param  Proration|null  $proration  Set for the first period of a subscription that starts in the middle of it.
     *
     * @throws InvalidArgumentException for a count below zero, an unknown catalog, a profile that does not go with the plan or a negotiated profile with no price.
     */
    public static function for(
        Plan $plan,
        Profile $profile,
        int $seats,
        int $centers,
        string $catalogVersion,
        int $eligibleIntegralCompanies = 0,
        ?int $negotiatedCents = null,
        ?Proration $proration = null,
    ): QuoteResult {
        $catalog = Catalog::version($catalogVersion);
        self::guard($plan, $profile, $seats, $centers, $negotiatedCents);

        $base = $negotiatedCents === null ? self::tableAmount($catalog, $plan, $profile, $seats) : self::negotiated($negotiatedCents);

        if ($base === null) {
            return new QuoteResult($catalog->currency(), $catalog->name(), [], true, null, null, null, 'Más de '.$catalog->quotationAbove($profile).' personas: requiere cotización');
        }

        $lines = [];
        $planLine = self::scaled($base, $proration);
        $lines[] = $planLine;

        if ($negotiatedCents === null && $profile === Profile::ChannelIntegral && $eligibleIntegralCompanies >= $catalog->integralMinimumCompanies()) {
            $lines[] = self::discount($catalog, $planLine->amountCents, $eligibleIntegralCompanies);
        }

        $extra = max(0, $centers - $catalog->includedCenters($plan));

        if ($extra > 0) {
            $lines[] = self::scaled(new QuoteLine('extra_centers', 'Centros adicionales', $extra * $catalog->extraCenterCents(), self::centersText($catalog, $plan, $extra)), $proration);
        }

        $subtotal = array_sum(array_map(fn (QuoteLine $line): int => $line->amountCents, $lines));
        $vat = self::divide($subtotal * $catalog->vatPercent(), 100);

        return new QuoteResult($catalog->currency(), $catalog->name(), $lines, false, $subtotal, $vat, $subtotal + $vat, 'Subtotal más '.$catalog->vatPercent().'% de IVA');
    }

    /** Rounds numerator over denominator to the nearest whole, half up; the numerator is never negative here. */
    public static function divide(int $numerator, int $denominator): int
    {
        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    private static function guard(Plan $plan, Profile $profile, int $seats, int $centers, ?int $negotiatedCents): void
    {
        if ($seats < 0 || $centers < 0) {
            throw new InvalidArgumentException('Las personas y los centros no pueden ser negativos.');
        }

        $required = $profile->requiredPlan();

        if ($required !== null && $required !== $plan) {
            throw new InvalidArgumentException("{$profile->label()} solo va con el plan {$required->label()}.");
        }

        if ($profile === Profile::Negotiated && $negotiatedCents === null) {
            throw new InvalidArgumentException('El perfil negociado necesita un precio negociado.');
        }
    }

    /** The plan line from the table; null when the table does not reach that many people. */
    private static function tableAmount(Catalog $catalog, Plan $plan, Profile $profile, int $seats): ?QuoteLine
    {
        if ($profile === Profile::Negotiated || $seats > $catalog->quotationAbove($profile)) {
            return null;
        }

        if ($profile === Profile::ChannelCompliance) {
            foreach ($catalog->complianceBands() as $upTo => $cents) {
                if ($seats <= $upTo) {
                    return new QuoteLine('plan', $plan->label(), $cents, "Banda de canal hasta {$upTo} personas: ".Money::format($cents));
                }
            }

            return null;
        }

        $byRate = $seats * $catalog->rateCents($plan);
        $minimum = $catalog->minimumCents($plan);
        $text = "{$seats} personas × ".Money::format($catalog->rateCents($plan)).' = '.Money::format($byRate);

        return $byRate >= $minimum
            ? new QuoteLine('plan', $plan->label(), $byRate, $text)
            : new QuoteLine('plan', $plan->label(), $minimum, 'Mínimo del plan: '.Money::format($minimum)." ({$text})");
    }

    private static function negotiated(int $cents): QuoteLine
    {
        return new QuoteLine('plan', 'Precio negociado', $cents, 'Precio negociado: '.Money::format($cents));
    }

    /** The line for its share of the period; a whole period is the line as it is. */
    private static function scaled(QuoteLine $line, ?Proration $proration): QuoteLine
    {
        if ($proration === null || $proration->daysRemaining === $proration->periodDays) {
            return $line;
        }

        return new QuoteLine(
            $line->code,
            $line->label,
            self::divide($line->amountCents * $proration->daysRemaining, $proration->periodDays),
            "{$line->explanation}, {$proration->daysRemaining} de {$proration->periodDays} días",
        );
    }

    /** The discount of the channel, from the plan line already rounded (and prorated). */
    private static function discount(Catalog $catalog, int $planCents, int $companies): QuoteLine
    {
        $percent = $catalog->integralDiscountPercent();
        $discounted = self::divide($planCents * (100 - $percent), 100);

        return new QuoteLine('channel_discount', 'Descuento de canal', $discounted - $planCents, "{$percent}% de descuento de canal: {$companies} empresas con Integral pagado");
    }

    private static function centersText(Catalog $catalog, Plan $plan, int $extra): string
    {
        $included = $catalog->includedCenters($plan);
        $price = Money::format($catalog->extraCenterCents());

        return "{$extra} ".($extra === 1 ? 'centro más' : 'centros más')." de los {$included} incluidos × {$price} = ".Money::format($extra * $catalog->extraCenterCents());
    }
}
