<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

/**
 * Which bag an email a module sends belongs to. Campaign email (invitations, reminders, digests) counts against the company's
 * monthly quota; essential email (access, recovery, security, accidents) is free and never counted. Every notification declares it.
 */
interface MailBag
{
    public const string CAMPAIGN = 'campaign';

    public const string ESSENTIAL = 'essential';

    public function mailBag(): string;
}
