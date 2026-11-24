<?php

declare(strict_types=1);

namespace App\Modules\Telework\Listeners;

use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A new version of the policy or the risk information was published: the people it is for are asked to acknowledge it.
 * Queued, after the commit: a center of hundreds of people must not hold up the publication's request.
 */
final class RequestOnPublication implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    /** The job carries its own tries: Horizon's supervisors run with one, and a failure here would lose the request and its mail. */
    public int $tries = 3;

    /** @var list<int> Seconds before the second and the third try. */
    public array $backoff = [60, 300];

    public function __construct(private Companies $companies, private RequestAcknowledgements $request) {}

    public function handle(DocumentPublished $event): void
    {
        if ($this->companies->enter($event->tenantId)->stopped('nom037')) {
            return;
        }

        $this->request->handle($event->documentIds, retrying: $this->attempts() > 1);
    }
}
