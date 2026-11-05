<?php

declare(strict_types=1);

namespace App\Modules\Seating\Contracts;

use App\Modules\Seating\Data\SeatingDeclaration;

/**
 * «¿Te aplica la Ley Silla?» of the current company's work centers (rediseño v2, decisión A2), for the pages that ask it
 * of several standards at once. The declaration is Ley Silla's own: declaring again replaces it, audited and logged.
 */
interface SeatingApplicability
{
    /**
     * @param  list<string>  $workCenterIds
     * @return array<string, SeatingDeclaration> by work center id; one that never declared, or of another company, is left out
     */
    public function declarations(array $workCenterIds): array;

    /**
     * Someone works standing, in a sector of sectors(); or nobody does, and the sector declared before (if any) stays.
     */
    public function declare(string $workCenterId, bool $standing, ?string $sector, int $userId): void;

    /** @return list<string> the sectors a center may declare */
    public function sectors(): array;
}
