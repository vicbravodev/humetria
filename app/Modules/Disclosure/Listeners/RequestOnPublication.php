<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Listeners;

use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Disclosure\Events\PolicyPublished;
use App\Modules\Tenancy\Contracts\Companies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * A version of the policy was published: the people of its centers are asked to confirm it. Queued, after the commit: a
 * center of hundreds of people must not hold up the publication's request.
 */
final class RequestOnPublication implements ShouldQueue, ShouldQueueAfterCommit
{
    public function __construct(private Companies $companies, private RequestAcknowledgments $request) {}

    public function handle(PolicyPublished $event): void
    {
        $this->companies->enter($event->tenantId);
        $this->request->handle($event->documentId);
    }
}
