<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Enums;

/**
 * Where a person's survey link stands: waiting in the queue, sent, failed after its retries, or not sendable for lack of
 * an email.
 */
enum InvitationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case NoEmail = 'no_email';
}
