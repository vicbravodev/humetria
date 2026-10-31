<?php

declare(strict_types=1);

namespace App\Modules\Telework\Listeners;

use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Events\DocumentPublished;
use App\Modules\Tenancy\Contracts\Companies;

/** A new version of the policy or the risk information was published: the people it is for are asked to acknowledge it. */
final readonly class RequestOnPublication
{
    public function __construct(private Companies $companies, private RequestAcknowledgements $request) {}

    public function handle(DocumentPublished $event): void
    {
        $this->companies->enter($event->tenantId);
        $this->request->handle($event->documentIds);
    }
}
