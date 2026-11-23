<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Actions\RemoveMember;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Events\MemberPermissionsChanged;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

mutates(GrantMemberPermissions::class, RemoveMember::class, CompanyRoles::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->person = memberOf($this->tenant, TenantRole::Viewer);
    $this->membership = TenantMembership::query()->where('tenant_id', $this->tenant->id)->where('user_id', $this->person->id)->sole();
    $this->grant = fn (array $permissions, ?TenantMembership $member = null) => app(GrantMemberPermissions::class)->handle($member ?? $this->membership, $permissions, $this->owner);
});

it('adds direct permissions on top of the role, and says where each comes from', function (): void {
    ($this->grant)(['manage-seating']);
    $roles = resolve(CompanyRoles::class);

    expect($roles->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-seating'])
        ->and($roles->effectivePermissions($this->person, $this->tenant))->toMatchArray(['view-dashboard' => 'role', 'manage-seating' => 'direct']);
});

it('reports a permission that role and direct grant share as coming from the role', function (): void {
    ($this->grant)(['view-dashboard']);

    expect(resolve(CompanyRoles::class)->effectivePermissions($this->person, $this->tenant)['view-dashboard'])->toBe('role');
});

it('replaces the previous direct permissions', function (): void {
    ($this->grant)(['manage-seating', 'manage-vacations']);
    ($this->grant)(['manage-vacations']);

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-vacations']);
});

it('keeps direct permissions inside the company and leaves the request team as it was', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->person)->role(TenantRole::Viewer)->create();
    ($this->grant)(['manage-seating']);
    $registrar = resolve(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($other->id);

    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $other))->toBe([])
        ->and(resolve(CompanyRoles::class)->effectivePermissions($this->person, $other))->not->toHaveKey('manage-seating')
        ->and($this->person->fresh()?->can('manage-seating'))->toBeFalse()
        ->and($registrar->getPermissionsTeamId())->toBe($other->id);

    $registrar->setPermissionsTeamId($this->tenant->id);
    expect($this->person->fresh()?->can('manage-seating'))->toBeTrue();
});

it('opens a gated route only in the company that granted the permission', function (): void {
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($other)->for($this->person)->role(TenantRole::Viewer)->create();
    $centerA = WorkCenter::factory()->for($this->tenant)->create();
    $centerB = WorkCenter::factory()->for($other)->create();
    ($this->grant)(['manage-seating']);

    $this->actingAs($this->person)->get(route('tenant.work-centers.applies', [$this->tenant, $centerA->id]))->assertOk();
    $this->actingAs($this->person)->get(route('tenant.work-centers.applies', [$other, $centerB->id]))->assertForbidden();
});

it('ignores a direct permission whose module the company does not have', function (): void {
    // Granted while the company still had the module; the plan changes afterwards.
    resolve(CompanyRoles::class)->syncDirectPermissions($this->person, $this->tenant, ['manage-vacations']);
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    expect(resolve(CompanyRoles::class)->effectivePermissions($this->person, $this->tenant))->not->toHaveKey('manage-vacations')
        ->and(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe(['manage-vacations']);
});

it('rejects permissions the company cannot hand out', function (array $permissions): void {
    expect(fn () => ($this->grant)($permissions))->toThrow(InvalidArgumentException::class);
    expect(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $this->tenant))->toBe([]);
})->with([
    'owner only' => [['manage-members']],
    'unknown' => [['fly-to-moon']],
]);

it('announces the change, logs it and audits it, flagging sensitive permissions', function (): void {
    Event::fake([MemberPermissionsChanged::class]);
    Log::spy();
    ($this->grant)(['view-compensation']);

    Event::assertDispatched(MemberPermissionsChanged::class, fn (MemberPermissionsChanged $event): bool => $event->tenantId === $this->tenant->id && $event->userId === $this->person->id);
    Log::shouldHaveReceived('info')->with('tenant_member.permissions_changed', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'member_user_id' => $this->person->id]);
    $entry = AuditEntry::query()->where('event', 'tenant_member.permissions_changed')->sole();
    expect($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->metadata)->toMatchArray(['member_user_id' => $this->person->id, 'added' => ['view-compensation'], 'removed' => [], 'sensitive' => true]);
});

it('removes only this company\'s direct permissions when the person leaves it', function (): void {
    $other = Tenant::factory()->create();
    $otherMembership = TenantMembership::factory()->for($other)->for($this->person)->role(TenantRole::Viewer)->create();
    ($this->grant)(['manage-seating']);
    ($this->grant)(['manage-vacations'], $otherMembership);

    app(RemoveMember::class)->handle($this->membership, $this->owner);

    expect(DB::table('model_has_permissions')->where('model_id', $this->person->id)->pluck('tenant_id')->all())->toBe([$other->id])
        ->and(resolve(CompanyRoles::class)->directPermissionsOf($this->person, $other))->toBe(['manage-vacations']);
});
