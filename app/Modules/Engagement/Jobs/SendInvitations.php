<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the survey link of a block of people. It runs in the company that queued it (its id travels in the job's Context).
 */
final class SendInvitations implements ShouldQueue
{
    use Batchable, Queueable;

    public int $maxExceptions = 3;

    /**
     * @param  list<string>  $invitationIds
     */
    public function __construct(
        public readonly string $surveyId,
        public readonly string $invitationBatch,
        public readonly array $invitationIds,
    ) {}
}
