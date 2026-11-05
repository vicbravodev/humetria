<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

/**
 * «¿Te aplica la NOM-037?» of the current company's work centers (rediseño v2, decisión A2), for the pages that ask it of
 * several standards at once: whether someone works away from the center more than 40 % of their time. The declaration is
 * NOM-037's own: declaring again replaces it, audited and logged.
 */
interface TeleworkApplicability
{
    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, bool> by work center id; one that never declared, or of another company, is left out
     */
    public function declarations(array $workCenterIds): array;

    public function declare(string $workCenterId, bool $hasTeleworkers, int $userId): void;
}
