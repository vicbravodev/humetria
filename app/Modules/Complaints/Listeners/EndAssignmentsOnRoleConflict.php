<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Listeners;

use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Tenancy\Contracts\MemberLookup;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Events\MemberRoleChanged;
use App\Modules\Tenancy\Events\RolePermissionsChanged;
use Illuminate\Support\Facades\Log;

/**
 * A person who becomes who a case is against stops holding it (criterio C-45): it shows as unassigned and can be routed
 * again. Being HR is holding its permission, by role or directly, unless one is the owner, who is management.
 */
final readonly class EndAssignmentsOnRoleConflict
{
    public function __construct(private MemberLookup $members) {}

    public function handle(MemberRoleChanged|MemberPermissionsChanged $event): void
    {
        $permissions = $this->members->permissionsOf($event->tenantId, $event->userId);
        $this->end($event->tenantId, [$event->userId], $this->members->isOwner($event->tenantId, $event->userId) ? 'owner' : null, [$event->userId => $permissions]);
    }

    /** What a company's own role gives changed: its holders are read together, and none of them is the owner. */
    public function handleRole(RolePermissionsChanged $event): void
    {
        $this->end($event->tenantId, $event->userIds, null, $this->members->permissionsOfMany($event->tenantId, $event->userIds));
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<int, list<string>>  $permissions  by user id
     */
    private function end(string $tenantId, array $userIds, ?string $role, array $permissions): void
    {
        foreach (RespondentKind::cases() as $kind) {
            $conflicting = array_values(array_filter($userIds, fn (int $userId): bool => $kind->excludes($role, $permissions[$userId] ?? [])));

            if ($conflicting === []) {
                continue;
            }

            $ended = ComplaintAssignment::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', $conflicting)
                ->whereNull('ended_on')
                ->whereIn('complaint_id', Complaint::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->where('respondent_kind', $kind->value)->select('id'))
                ->update(['ended_on' => Dates::today()->toDateString(), 'end_reason' => 'role_conflict']);

            if ($ended > 0) {
                Log::info('complaints.assignments_ended', ['user_ids' => $conflicting, 'reason' => 'role_conflict', 'count' => $ended]);
            }
        }
    }
}
