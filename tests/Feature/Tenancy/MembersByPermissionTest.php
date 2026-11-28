<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyMembers;
use App\Modules\Tenancy\Support\ModuleStates;
use Illuminate\Support\Facades\DB;

mutates(CompanyMembers::class);

// Who holds a permission is said by the role and by what was given to the person directly.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->given = memberOf($this->tenant, TenantRole::Viewer);
    $this->plain = memberOf($this->tenant, TenantRole::Viewer);
    workIn($this->tenant);
});

/** @param list<MemberSummary> $members @return list<int> */
function userIdsOf(array $members): array
{
    return array_map(fn (MemberSummary $member): int => $member->userId, $members);
}

it('lists among the holders of a permission whoever was given it directly, besides those whose role has it', function (): void {
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->given->id)->sole(), ['handle-complaints', 'manage-organization'], $this->owner);
    $members = resolve(Members::class);

    expect(userIdsOf($members->complaintHandlers()))->toContain($this->given->id, $this->owner->id)->not->toContain($this->plain->id)
        ->and(userIdsOf($members->holding('manage-organization')))->toContain($this->given->id, $this->owner->id)->not->toContain($this->plain->id);
});

it('does not count a permission given in another company', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->plain)->role(TenantRole::Viewer)->create();
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $other->id)->where('user_id', $this->plain->id)->sole(), ['handle-complaints'], $this->owner);

    expect(userIdsOf(resolve(Members::class)->complaintHandlers()))->not->toContain($this->plain->id);
});

it('leaves out whoever holds a permission of a tool the company has not contracted, as the effective permissions do', function (): void {
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->given->id)->sole(), ['view-vacations'], $this->owner);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);
    resolve(ModuleStates::class)->forget();
    resolve(CompanyMembers::class)->forget();

    expect(resolve(Members::class)->holding('view-vacations'))->toBe([]);
});

it('asks who holds a permission once per request, and again after a role or permission changes', function (): void {
    $members = resolve(Members::class);
    $members->holding('manage-organization');

    DB::enableQueryLog();
    $members->holding('manage-organization');
    $repeated = count(DB::getQueryLog());
    DB::disableQueryLog();

    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->given->id)->sole(), ['manage-organization'], $this->owner);

    expect($repeated)->toBe(0)
        ->and(userIdsOf($members->holding('manage-organization')))->toContain($this->given->id);
});

it('lists as managers of the complaint channel and as viewers of Guía I whoever holds that permission, and nobody else', function (): void {
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('user_id', $this->given->id)->sole(), ['manage-complaint-channel', 'view-trauma-screenings'], $this->owner);
    $members = resolve(Members::class);

    expect(userIdsOf($members->complaintManagers()))->toContain($this->given->id, $this->owner->id)->not->toContain($this->plain->id)
        ->and(userIdsOf($members->screeningViewers()))->toContain($this->given->id, $this->owner->id)->not->toContain($this->plain->id)
        ->and(userIdsOf($members->reviewers()))->not->toContain($this->given->id, $this->plain->id);
});
