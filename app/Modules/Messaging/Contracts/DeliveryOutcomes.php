<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Contracts;

/**
 * How a set of messages ended, for the reconciliation of the month (what was charged against what arrived). Only ids come out:
 * never a phone, a person or a time.
 */
interface DeliveryOutcomes
{
    /**
     * Of these message ids, the ones whose status is not delivered or read. An id with no message (the retention may have deleted
     * it) is not said to be undelivered: nothing is known about it.
     *
     * @param  list<string>  $deliveryIds
     * @return list<string>
     */
    public function undelivered(array $deliveryIds): array;
}
