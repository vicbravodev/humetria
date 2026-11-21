<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/**
 * Where a person's survey link stands: waiting in the queue, sent, failed after its retries, or unreachable: no way to
 * contact them among the channels the company has on.
 */
enum InvitationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Unreachable = 'unreachable';
}
