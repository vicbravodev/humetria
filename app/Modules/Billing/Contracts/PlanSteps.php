<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/**
 * What the next size of a company's plan would cost, in words, for the screen that stops it at its limit. The amounts come from the
 * same quote the console uses; nothing else prices a plan.
 */
interface PlanSteps
{
    /**
     * "With 51 people the price goes from $2,490 to $3,990 a month, before VAT", or that the size needs a quotation; null when the price does not
     * change, the company has no plan, or its price is negotiated.
     *
     * @param  string  $resource  'seats' or 'centers'.
     * @param  int  $target  how many the company would have.
     */
    public function describe(string $tenantId, string $resource, int $target): ?string;
}
