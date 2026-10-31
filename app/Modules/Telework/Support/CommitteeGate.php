<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\CommitteeAssignment;

/**
 * Who sits on the Comisión of which work center of the current company. Validating and editing a center's checklist,
 * and a committee user seeing a center at all, need it.
 */
final readonly class CommitteeGate
{
    public function isMember(int $userId, string $workCenterId): bool
    {
        return CommitteeAssignment::query()->where('user_id', $userId)->where('work_center_id', $workCenterId)->exists();
    }

    /** @return list<string> */
    public function workCenterIds(int $userId): array
    {
        return array_values(CommitteeAssignment::query()->where('user_id', $userId)->orderBy('work_center_id')->get(['work_center_id'])->map(fn (CommitteeAssignment $assignment): string => $assignment->work_center_id)->all());
    }
}
