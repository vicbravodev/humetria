<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Listeners;

use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Support\Dates;
use App\Modules\Tenancy\Events\MemberRoleChanged;
use Illuminate\Support\Facades\Log;

/** A person who becomes the role a case is against stops holding it (criterio C-45): it shows as unassigned and can be routed again. */
final readonly class EndAssignmentsOnRoleConflict
{
    public function handle(MemberRoleChanged $event): void
    {
        $kinds = collect(RespondentKind::cases())->filter(fn (RespondentKind $kind): bool => $kind->excludedRole() === $event->role)->map(fn (RespondentKind $kind): string => $kind->value)->values()->all();

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
