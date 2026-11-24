<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\MessagePrice;

/**
 * The WhatsApp messages a company may send in the month: the lower of what its plan includes and the cap its owner set. A launch
 * reserves the messages it will send before it marks anything, all or none; each message that Meta accepts takes its place from
 * the hold, a failed one gives it back, and what nobody used is released every night. Without a plan a company has none.
 * The company is the current one.
 */
interface WhatsAppQuota
{
    /**
     * Holds `$count` messages of the current company, all or none, with one conditional statement.
     *
     * @throws \App\Modules\Billing\Exceptions\WhatsAppQuotaExceeded when they do not fit.
     */
    public function reserve(int $count): void;

    /** What one message is charged now: Meta's cost plus the margin. */
    public function price(): MessagePrice;
}
