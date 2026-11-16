<?php

declare(strict_types=1);

use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Listeners\EndAssignmentsOnRoleConflict;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\CaseAccess;
use App\Modules\Tenancy\Actions\ChangeMemberRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;

mutates(EndAssignmentsOnRoleConflict::class, CaseAccess::class);

// Audit C5: the conflict rule (criterio C-45) holds after an assignment too, when the role changes.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->outsider = memberOf($this->tenant, TenantRole::Complaints);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $this->outsider->id]);
    workIn($this->tenant);
});

/** A case of the kind, held by the person. */
function heldCase(object $test, RespondentKind $kind, object $person): Complaint
{
    $complaint = Complaint::factory()->create(['tenant_id' => $test->tenant->id, 'channel_id' => $test->channel->id, 'respondent_kind' => $kind]);
    ComplaintAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id]);

    return $complaint;
}

function openHolders(Complaint $complaint): int
{
    return ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->count();
}

it('ends the assignments against the new role of a member whose role changes, and leaves the others', function (): void {
    $against = heldCase($this, RespondentKind::Hr, $this->outsider);
    $other = heldCase($this, RespondentKind::Coworker, $this->outsider);

    resolve(ChangeMemberRole::class)->handle(TenantMembership::query()->where('user_id', $this->outsider->id)->sole(), TenantRole::Hr, $this->owner);

    $ended = ComplaintAssignment::query()->where('complaint_id', $against->id)->sole();
    expect($ended->end_reason)->toBe('role_conflict')->and($ended->ended_on)->not->toBeNull()
        ->and(openHolders($against))->toBe(0)
        ->and(openHolders($other))->toBe(1);
});

it('answers 404 on the case page and on every case action to a member whose role is the one the case is against, even with an open assignment', function (): void {
    $complaint = heldCase($this, RespondentKind::Hr, $this->hr);
    $this->actingAs($this->hr);

    expect(resolve(CaseAccess::class)->find($this->hr->id, $complaint->id))->toBeNull();
    $this->get(route('tenant.complaints.cases.show', [$this->tenant, $complaint->id]))->assertNotFound();

    foreach (['acknowledge', 'review', 'messages.store', 'notes.store', 'share', 'recuse', 'hold', 'close', 'reopen', 'export'] as $action) {
        $this->post(route("tenant.complaints.cases.{$action}", [$this->tenant, $complaint->id]), ['body' => 'x'])->assertNotFound();
    }
});

it('still opens a case of another kind to the same member', function (): void {
    $complaint = heldCase($this, RespondentKind::Coworker, $this->hr);

    expect(resolve(CaseAccess::class)->find($this->hr->id, $complaint->id)?->id)->toBe($complaint->id);
});
