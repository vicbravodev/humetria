<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Carbon\CarbonImmutable;
use LogicException;

/**
 * Meta's cost of a WhatsApp message on a date (the entry of `billing.whatsapp.costs` in force) and the price charged to the
 * company: the cost plus the margin, rounded up to the next micro.
 */
final class MessagingCost
{
    public function at(CarbonImmutable $when): UnitCost
    {
        $inForce = null;

        foreach (config()->array('billing.whatsapp.costs') as $entry) {
            if (! is_array($entry) || ! is_string($entry['from'] ?? null) || ! is_string($entry['currency'] ?? null) || ! is_int($entry['unit_cost_micros'] ?? null)) {
                throw new LogicException('billing.whatsapp.costs entries need from, currency and unit_cost_micros.');
            }

            if (CarbonImmutable::parse($entry['from'], config()->string('app.display_timezone'))->lessThanOrEqualTo($when) && ($inForce === null || $entry['from'] > $inForce['from'])) {
                $inForce = ['from' => $entry['from'], 'currency' => $entry['currency'], 'cost' => $entry['unit_cost_micros']];
            }
        }

        if ($inForce === null) {
            throw new LogicException('No WhatsApp cost is in force on '.$when->toDateString().'.');
        }

        $multiplier = 100 + config()->integer('billing.whatsapp.margin_percent');

        return new UnitCost($inForce['cost'], intdiv($inForce['cost'] * $multiplier + 99, 100), $inForce['currency']);
    }
}
