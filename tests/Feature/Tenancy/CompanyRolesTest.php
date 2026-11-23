<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\DeleteCompanyRole;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\CompanyRoleInUse;
use App\Modules\Tenancy\Exceptions\CompanyRoleLimitReached;
use App\Modules\Tenancy\Exceptions\NotACompanyRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

mutates(CreateCompanyRole::class, UpdateCompanyRole::class, DeleteCompanyRole::class, CompanyRoles::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    $this->create = fn (string $label = 'Auditoría', array $permissions = ['view-dashboard'], ?TenantRole $basedOn = null, ?Tenant $tenant = null): Role => app(CreateCompanyRole::class)->handle($tenant ?? $this->tenant, $label, $permissions, $basedOn, $this->owner);
});

it('creates a role named custom_<uuid> with its label, origin and permissions', function (): void {
    Log::spy();
    $role = ($this->create)('Auditoría', ['view-dashboard', 'manage-organization'], TenantRole::Hr);

    expect($role->name)->toMatch('/^custom_[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($role->getAttribute('label'))->toBe('Auditoría')
        ->and($role->getAttribute('based_on'))->toBe('hr')
        ->and($role->getAttribute('tenant_id'))->toBe($this->tenant->id)
        ->and($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['view-dashboard', 'manage-organization']);

    Log::shouldHaveReceived('info')->with('tenant_role.created', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'role_id' => $role->id]);
    $entry = AuditEntry::query()->where('event', 'tenant_role.created')->sole();
    expect($entry->tenant_id)->toBe($this->tenant->id)->and($entry->metadata['sensitive'])->toBeFalse();
});

it('leaves a role labelled owner apart from the owner template', function (): void {
    $role = ($this->create)('owner');
    $person = User::factory()->create();
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $this->tenant->id]);

    expect($person->hasRole('owner'))->toBeFalse()
        ->and($role->name)->not->toBe('owner')
        ->and(Role::query()->where('name', 'owner')->whereNull('tenant_id')->exists())->toBeTrue();
});

it('rejects permissions the company cannot hand out', function (array $permissions): void {
    expect(fn () => ($this->create)('X', $permissions))->toThrow(InvalidArgumentException::class);
    expect(Role::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
})->with([
    'owner only' => [['manage-members']],
    'unknown' => [['fly-to-moon']],
]);

it('rejects a permission of a module the company did not contract', function (): void {
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    expect(fn () => ($this->create)('X', ['view-vacations']))->toThrow(InvalidArgumentException::class);
});

it('rejects a permission that is not for a union', function (): void {
    $union = Tenant::factory()->union()->create();

    expect(fn () => ($this->create)('X', ['view-dashboard'], null, $union))->toThrow(InvalidArgumentException::class);
    expect(($this->create)('X', ['view-compensation'], null, $union)->permissions)->toHaveCount(1);
});

it('allows the configured number of custom roles (20 by default) and refuses one more', function (): void {
    expect(config('tenancy.max_custom_roles'))->toBe(20);
    config(['tenancy.max_custom_roles' => 2]);

    foreach (range(1, 2) as $n) {
        ($this->create)("Rol $n");
    }
    $other = Tenant::factory()->create();

    expect(fn () => ($this->create)('Uno más'))->toThrow(CompanyRoleLimitReached::class)
        ->and(($this->create)('Otra empresa', ['view-dashboard'], null, $other))->toBeInstanceOf(Role::class);
});

it('edits label and permissions with a log and an audit entry', function (): void {
    $role = ($this->create)('Auditoría', ['view-dashboard']);
    Log::spy();

    app(UpdateCompanyRole::class)->handle($role, 'Auditoría interna', ['view-work-centers'], $this->owner);

    $role->refresh();
    expect($role->getAttribute('label'))->toBe('Auditoría interna')
        ->and($role->permissions->pluck('name')->all())->toBe(['view-work-centers']);
    Log::shouldHaveReceived('info')->with('tenant_role.updated', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'role_id' => $role->id]);
    $entry = AuditEntry::query()->where('event', 'tenant_role.updated')->sole();
    expect($entry->metadata['added'])->toBe(['view-work-centers'])
        ->and($entry->metadata['removed'])->toBe(['view-dashboard'])
        ->and($entry->metadata['sensitive'])->toBeFalse()
        ->and($entry->metadata['holders'])->toBe([]);
});

it('flags the audit entry as sensitive when a sensitive permission is added to a role with people', function (): void {
    $role = ($this->create)('Auditoría', ['view-dashboard']);
    $person = User::factory()->create();
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $this->tenant->id]);

    app(UpdateCompanyRole::class)->handle($role, 'Auditoría', ['view-dashboard', 'view-compensation'], $this->owner);

    $entry = AuditEntry::query()->where('event', 'tenant_role.updated')->sole();
    expect($entry->metadata['sensitive'])->toBeTrue()->and($entry->metadata['holders'])->toBe([$person->id]);
});

it('flags a created role with a sensitive permission', function (): void {
    ($this->create)('RH confidencial', ['handle-complaints']);

    expect(AuditEntry::query()->where('event', 'tenant_role.created')->sole()->metadata['sensitive'])->toBeTrue();
});

it('does not flag an edit that only removes a sensitive permission', function (): void {
    $role = ($this->create)('X', ['view-compensation']);

    app(UpdateCompanyRole::class)->handle($role, 'X', ['view-dashboard'], $this->owner);

    expect(AuditEntry::query()->where('event', 'tenant_role.updated')->sole()->metadata['sensitive'])->toBeFalse();
});

it('rejects invalid permissions on edit', function (): void {
    $role = ($this->create)();

    expect(fn () => app(UpdateCompanyRole::class)->handle($role, 'X', ['manage-members'], $this->owner))->toThrow(InvalidArgumentException::class);
});

it('deletes a role nobody uses, with a log and an audit entry', function (): void {
    $role = ($this->create)();
    Log::spy();

    app(DeleteCompanyRole::class)->handle($role, $this->owner);

    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
    Log::shouldHaveReceived('info')->with('tenant_role.deleted', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'role_id' => $role->id]);
    expect(AuditEntry::query()->where('event', 'tenant_role.deleted')->exists())->toBeTrue();
});

it('refuses to delete a role a person holds', function (): void {
    $role = ($this->create)();
    $person = User::factory()->create();
    DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $this->tenant->id]);

    expect(fn () => app(DeleteCompanyRole::class)->handle($role, $this->owner))->toThrow(CompanyRoleInUse::class);
    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('lists only the company\'s own custom roles and finds a person\'s role, template or custom', function (): void {
    $mine = ($this->create)('Beta');
    ($this->create)('Alfa');
    $theirs = ($this->create)('De B', ['view-dashboard'], null, $other = Tenant::factory()->create());
    $roles = resolve(CompanyRoles::class);
    $person = User::factory()->create();
    $templated = User::factory()->create();
    DB::table('model_has_roles')->insert(['role_id' => $mine->id, 'model_type' => $person->getMorphClass(), 'model_id' => $person->id, 'tenant_id' => $this->tenant->id]);
    $roles->assign($templated, $this->tenant, TenantRole::Hr);

    expect($roles->customRoles($this->tenant)->map(fn (Role $role) => $role->getAttribute('label'))->all())->toBe(['Alfa', 'Beta'])
        ->and($roles->customRoles($other)->pluck('id')->all())->toBe([$theirs->id])
        ->and($roles->roleOf($person, $this->tenant)?->id)->toBe($mine->id)
        ->and($roles->roleOf($templated, $this->tenant)?->name)->toBe('hr')
        ->and($roles->roleOf($person, $other))->toBeNull();
});

it('never deletes or edits a template role', function (): void {
    $viewer = Role::query()->where('name', 'viewer')->whereNull('tenant_id')->sole();
    $permissions = $viewer->permissions->pluck('name')->sort()->values()->all();

    expect(fn () => app(DeleteCompanyRole::class)->handle($viewer, $this->owner))->toThrow(NotACompanyRole::class)
        ->and(fn () => app(UpdateCompanyRole::class)->handle($viewer, 'Hackeado', ['view-dashboard'], $this->owner))->toThrow(NotACompanyRole::class);

    $viewer->refresh();
    expect($viewer->getAttribute('label'))->not->toBe('Hackeado')
        ->and($viewer->permissions->pluck('name')->sort()->values()->all())->toBe($permissions)
        ->and(AuditEntry::query()->where('event', 'like', 'tenant_role.%')->exists())->toBeFalse();
});

it('does not touch a role through a tenant_id-less custom name either', function (): void {
    $role = Role::query()->create(['name' => 'custom_orphan', 'guard_name' => 'web']);

    expect(fn () => app(DeleteCompanyRole::class)->handle($role, $this->owner))->toThrow(NotACompanyRole::class);
});
