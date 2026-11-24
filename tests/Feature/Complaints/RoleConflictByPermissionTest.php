<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Complaints\Enums\RespondentKind;
use App\Modules\Complaints\Listeners\EndAssignmentsOnRoleConflict;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\CaseAccess;
use App\Modules\Complaints\Support\EvidencePages;
use App\Modules\Complaints\Support\HandlerCoverage;
use App\Modules\Tenancy\Actions\ChangeMemberRole;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

mutates(EndAssignmentsOnRoleConflict::class, RespondentKind::class, CaseAccess::class, HandlerCoverage::class, EvidencePages::class);

// Criterio C-45 by permission: holding manage-organization is being HR, being owner is being management.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    workIn($this->tenant);
});

/** A person of the company whose role is one of its own, made of `$permissions`. */
function customRoleMember(Tenant $tenant, User $by, array $permissions = ['handle-complaints'], ?User $person = null): User
{
    $role = app(CreateCompanyRole::class)->handle($tenant, 'Atención externa', $permissions, null, $by);
    $person ??= User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($person)->role(TenantRole::Viewer)->create();
    DB::table('model_has_roles')->where('tenant_id', $tenant->id)->where('model_id', $person->id)->update(['role_id' => $role->id]);

    return $person;
}

/** A case of the kind in the company, held by the person. */
function caseHeldBy(Tenant $tenant, RespondentKind $kind, User $person): ComplaintAssignment
{
    $channel = ComplaintChannel::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->first()
        ?? ComplaintChannel::factory()->forTenant($tenant->id)->active()->create();
    $complaint = Complaint::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'respondent_kind' => $kind]);

    return ComplaintAssignment::factory()->create(['tenant_id' => $tenant->id, 'complaint_id' => $complaint->id, 'user_id' => $person->id]);
}

function grantDirect(Tenant $tenant, User $person, array $permissions, User $by): void
{
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $person->id)->sole(), $permissions, $by);
}

it('says which permission keeps a person from a case of each kind', function (): void {
    expect(RespondentKind::Hr->excludedPermission())->toBe('manage-organization')
        ->and(array_filter(RespondentKind::cases(), fn (RespondentKind $kind): bool => $kind !== RespondentKind::Hr && $kind->excludedPermission() !== null))->toBe([]);
});

it('ends the cases against HR of a person with a role of their own who is given manage-organization directly', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $other = caseHeldBy($this->tenant, RespondentKind::Coworker, $person);

    grantDirect($this->tenant, $person, ['manage-organization'], $this->owner);

    expect($againstHr->refresh()->end_reason)->toBe('role_conflict')
        ->and($againstHr->ended_on)->not->toBeNull()
        ->and($other->refresh()->ended_on)->toBeNull();
});

it('does not bring the ended cases back when the direct permission is taken away', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    grantDirect($this->tenant, $person, ['manage-organization'], $this->owner);

    grantDirect($this->tenant, $person, [], $this->owner);

    expect($againstHr->refresh()->end_reason)->toBe('role_conflict')->and($againstHr->ended_on)->not->toBeNull();
});

it('leaves a person whose role of their own and direct permissions lack manage-organization', function (): void {
    $person = customRoleMember($this->tenant, $this->owner, ['handle-complaints', 'view-dashboard']);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $againstManagement = caseHeldBy($this->tenant, RespondentKind::Management, $person);

    grantDirect($this->tenant, $person, ['view-seating'], $this->owner);

    expect($againstHr->refresh()->ended_on)->toBeNull()->and($againstManagement->refresh()->ended_on)->toBeNull();
});

it('leaves the cases the same person holds in another company', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $other = Tenant::factory()->create();
    customRoleMember($other, $this->owner, person: $person);
    $elsewhere = caseHeldBy($other, RespondentKind::Hr, $person);
    $here = caseHeldBy($this->tenant, RespondentKind::Hr, $person);

    grantDirect($this->tenant, $person, ['manage-organization'], $this->owner);

    expect($here->refresh()->ended_on)->not->toBeNull()->and($elsewhere->refresh()->ended_on)->toBeNull();
});

it('ends the cases against management of a person who becomes owner, and leaves those against HR as before', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $againstManagement = caseHeldBy($this->tenant, RespondentKind::Management, $person);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $other = caseHeldBy($this->tenant, RespondentKind::Coworker, $person);

    resolve(ChangeMemberRole::class)->handle(TenantMembership::query()->where('user_id', $person->id)->sole(), TenantRole::Owner, $this->owner);

    expect($againstManagement->refresh()->end_reason)->toBe('role_conflict')
        ->and($againstHr->refresh()->ended_on)->toBeNull()
        ->and($other->refresh()->ended_on)->toBeNull();
});

it('leaves the cases against HR of the owner when the owner is given a permission directly', function (): void {
    $owner = memberOf($this->tenant, TenantRole::Owner);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $owner);

    grantDirect($this->tenant, $owner, ['view-seating'], $this->owner);

    expect($againstHr->refresh()->ended_on)->toBeNull()
        ->and(resolve(CaseAccess::class)->find($owner->id, $againstHr->complaint_id)?->id)->toBe($againstHr->complaint_id);
});

/** The company's outside handler, designated, who was then given manage-organization directly. */
function outsiderGivenHr(Tenant $tenant, User $by): User
{
    $person = memberOf($tenant, TenantRole::Complaints);
    ComplaintHandler::factory()->company($tenant->id)->create(['user_id' => $person->id]);
    grantDirect($tenant, $person, ['manage-organization'], $by);

    return $person;
}

it('keeps a person given manage-organization out of a case against HR routed to them afterwards, and not out of others', function (): void {
    $person = outsiderGivenHr($this->tenant, $this->owner);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $other = caseHeldBy($this->tenant, RespondentKind::Coworker, $person);

    expect(resolve(CaseAccess::class)->find($person->id, $againstHr->complaint_id))->toBeNull()
        ->and(resolve(CaseAccess::class)->find($person->id, $other->complaint_id)?->id)->toBe($other->complaint_id);
    $this->actingAs($person)->get(route('tenant.complaints.cases.show', [$this->tenant, $againstHr->complaint_id]))->assertNotFound();
});

it('does not route a complaint against HR to a handler given manage-organization', function (): void {
    $person = outsiderGivenHr($this->tenant, $this->owner);
    $coverage = resolve(HandlerCoverage::class);

    expect($coverage->handlerIds(RespondentKind::Hr, null))->not->toContain($person->id)
        ->and($coverage->handlerIds(RespondentKind::Coworker, null))->toContain($person->id)
        ->and($coverage->counts()['hr'])->toBe(0);
});

it('does not count a handler given manage-organization as independent', function (): void {
    outsiderGivenHr($this->tenant, $this->owner);

    expect(resolve(EvidencePages::class)->show()['handlers'])->toBe(['count' => 1, 'independent' => false]);
});

it('ends the cases against HR of the people of a role of its own when manage-organization is added to the role', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $role = Role::query()->where('tenant_id', $this->tenant->id)->sole();

    app(UpdateCompanyRole::class)->handle($role, 'Atención externa', ['handle-complaints', 'manage-organization'], $this->owner);

    expect($againstHr->refresh()->end_reason)->toBe('role_conflict');
});

it('lets a person with a role of their own that handles complaints be designated and open their cases', function (): void {
    $person = customRoleMember($this->tenant, $this->owner);
    $owner = memberOf($this->tenant, TenantRole::Owner);

    expect(collect(resolve(App\Modules\Tenancy\Contracts\Members::class)->complaintHandlers())->pluck('userId')->all())->toContain($person->id);
    $this->actingAs($owner)->post(route('tenant.complaints.handlers.store', $this->tenant), ['user_id' => $person->id, 'work_center_id' => ''])->assertSessionHasNoErrors();
    expect(ComplaintHandler::query()->where('user_id', $person->id)->exists())->toBeTrue();

    $case = caseHeldBy($this->tenant, RespondentKind::Coworker, $person);
    expect(resolve(CaseAccess::class)->find($person->id, $case->complaint_id)?->id)->toBe($case->complaint_id);
});

it('keeps a person whose role of their own has manage-organization out of the cases against HR', function (): void {
    $person = customRoleMember($this->tenant, $this->owner, ['handle-complaints', 'manage-organization']);
    $againstHr = caseHeldBy($this->tenant, RespondentKind::Hr, $person);
    $other = caseHeldBy($this->tenant, RespondentKind::Coworker, $person);

    expect(resolve(CaseAccess::class)->find($person->id, $againstHr->complaint_id))->toBeNull()
        ->and(resolve(CaseAccess::class)->find($person->id, $other->complaint_id)?->id)->toBe($other->complaint_id);
});
