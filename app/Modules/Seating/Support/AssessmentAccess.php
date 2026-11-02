<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Telework\Contracts\Committee;
use Illuminate\Support\Facades\Gate;

/**
 * Who may assess in a work center: whoever holds the permission AND sits on its Comisión (criterio C-07, num. 7.1 of the
 * NOM-019: the employer's representative is part of it, so an assigned owner or hr assesses too). Everyone else only reads.
 */
final readonly class AssessmentAccess
{
    public function __construct(private Committee $committee) {}

    public function canAssess(int $userId, string $workCenterId): bool
    {
        return Gate::allows('assess-seating') && $this->committee->isMember($userId, $workCenterId);
    }
}
