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
use Illuminate\Support\Facades\Log;

/**
 * A person who becomes who a case is against stops holding it (criterio C-45): it shows as unassigned and can be routed
 * again. Being HR is holding its permission, by role or directly; being management is becoming the owner.
 */
final readonly class EndAssignmentsOnRoleConflict
{
    public function __construct(private MemberLookup $members) {}

    public function handle(MemberRoleChanged|MemberPermissionsChanged $event): void
    {
        $permissions = $this->members->permissionsOf($event->tenantId, $event->userId);
        $role = $event instanceof MemberRoleChanged ? $event->role : null;
        $kinds = collect(RespondentKind::cases())
            ->filter(fn (RespondentKind $kind): bool => ($permission = $kind->excludedPermission()) !== null
                ? in_array($permission, $permissions, true)
                : $role !== null && $kind->excludedRole() === $role)
            ->map(fn (RespondentKind $kind): string => $kind->value)->values()->all();

        if ($kinds === []) {
            return;
        }

        $ended = ComplaintAssignment::query()->withoutGlobalScopes()
            ->where('tenant_id', $event->tenantId)
            ->where('user_id', $event->userId)
            ->whereNull('ended_on')
            ->whereIn('complaint_id', Complaint::query()->withoutGlobalScopes()->where('tenant_id', $event->tenantId)->whereIn('respondent_kind', $kinds)->select('id'))
            ->update(['ended_on' => Dates::today()->toDateString(), 'end_reason' => 'role_conflict']);

        if ($ended > 0) {
            Log::info('complaints.assignments_ended', ['user_id' => $event->userId, 'reason' => 'role_conflict', 'count' => $ended]);
        }
    }
}
