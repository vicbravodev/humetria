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
     * @param  list<PendingChecklist>  $sent  Already sent, waiting for the Comisión.
     */
    public function __construct(public array $checklists, public array $sent) {}
}
