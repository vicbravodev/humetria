<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;

/**
 * Who sits on the Comisión of which work center of the current company. Validating and editing a center's checklist,
 * and a committee user seeing a center at all, need it. A seat counts only while the person still holds a reviewing
 * role in the company: a demoted or removed member keeps the assignment row (so RH can see and end it) but not the access.
 */
final readonly class CommitteeGate
{
    public function __construct(private Members $members) {}

    public function isMember(int $userId, string $workCenterId): bool
    {
        return $this->isReviewer($userId)
            && CommitteeAssignment::query()->where('user_id', $userId)->where('work_center_id', $workCenterId)->exists();
    }

    /** @return list<string> */
    public function workCenterIds(int $userId): array
    {
        if (! $this->isReviewer($userId)) {
            return [];
        }

        return array_values(CommitteeAssignment::query()->where('user_id', $userId)->orderBy('work_center_id')->get(['work_center_id'])->map(fn (CommitteeAssignment $assignment): string => $assignment->work_center_id)->all());
    }

    private function isReviewer(int $userId): bool
    {
        return array_any($this->members->reviewers(), fn (MemberSummary $member): bool => $member->userId === $userId);
    }
}
