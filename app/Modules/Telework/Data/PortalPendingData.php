<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * What a person has to do in their portal, and what they already sent.
 */
final readonly class PortalPendingData
{
    /**
     * @param  list<PendingChecklist>  $checklists  Still to answer, one per workplace and section.
     * @param  list<PendingChecklist>  $sent  Already sent: waiting for the Comisión, or reviewed.
     * @param  list<PendingPhotoRequest>  $photos  Photos the Comisión asked for and nobody sent yet.
     * @param  list<PendingVisit>  $visits  Visits waiting for the person's authorization.
     * @param  list<PendingDocument>  $documents  Documents to read and confirm.
     */
    public function __construct(public array $checklists, public array $sent, public array $photos = [], public array $visits = [], public array $documents = []) {}
}
