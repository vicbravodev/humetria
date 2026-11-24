<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Tenancy\Contracts\Members;
use Illuminate\Support\Str;

/**
 * The only door to a case: a person finds it only if a current assignment names them. Another company's case, a made-up
 * id, a case of someone else and a case whose assignment ended all give the same nothing, which callers answer with a 404
 * (never a 403, which would say the case exists). Having the permission to handle complaints opens nothing.
 */
final readonly class CaseAccess
{
    public function __construct(private Members $members, private ExcludingPermissions $excluding) {}

    public function find(int $userId, string $complaintId): ?Complaint
    {
        if (! Str::isUuid($complaintId)) {
            return null;
        }

        $case = Complaint::query()
            ->whereIn('id', ComplaintAssignment::query()->where('user_id', $userId)->whereNull('ended_on')->select('complaint_id'))
            ->find($complaintId);
        $member = $case === null ? null : $this->members->find($userId);

        // Nobody handles a case against who they are (criterio C-45), also when the role or permission came after the assignment.
        return $member === null || $case->respondent_kind->excludes($member->role->value, $this->excluding->byMember()[$userId] ?? []) ? null : $case;
    }
}
