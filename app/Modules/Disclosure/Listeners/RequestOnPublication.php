<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Listeners;

use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Disclosure\Events\PolicyPublished;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A version of the policy was published: the people of its centers are asked to confirm it. Queued, after the commit: a
 * center of hundreds of people must not hold up the publication's request.
 */
final class RequestOnPublication implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    /** The job carries its own tries: Horizon's supervisors run with one, and a failure here would lose the request and its mail. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(private Companies $companies, private RequestAcknowledgments $request) {}

    public function handle(PolicyPublished $event): void
    {
        if ($this->companies->enter($event->tenantId)->stopped('nom035')) {
            return;
        }

        $this->request->handle($event->documentId, retrying: $this->attempts() > 1);
    }
}
