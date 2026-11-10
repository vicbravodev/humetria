<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/**
 * The campaign email a company may send in the month (8 per seat of its plan, 500 at least). A bulk send that already knows its
 * recipients reserves them here before it queues anything, inside the transaction that records the send: if they do not all fit,
 * nothing goes out and the person is told how many are missing. Each mail that does go out takes its place from the hold
 * (see the NotificationSent listener); what nobody used is released every night. Essential email is never reserved here, and a
 * company with no plan is not limited. The company is the current one.
 */
interface MailQuota
{
    /**
     * Holds `$count` mails of the current company, all or none, with one conditional statement.
     *
     * @throws \App\Modules\Billing\Exceptions\MailQuotaExceeded when they do not fit.
     */
    public function reserve(int $count): void;
}
