<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\ChangeMemberRole;
use App\Modules\Tenancy\Actions\InviteMember;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Exceptions\RoleNotAllowedInAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(InviteMember::class, ChangeMemberRole::class, TenantType::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function typedCompanyInput(array $overrides = []): array
{
    return [
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'trade_name' => 'Grupo Ejemplo',
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
        ...$overrides,
    ];
}

it('creates a union from the console and defaults to company', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), typedCompanyInput(['type' => 'union']))
        ->assertSessionHasNoErrors();
    $this->actingAs(superadmin())
        ->post(route('companies.store'), typedCompanyInput(['rfc' => 'ABC0101011A1', 'slug' => 'otra']))
        ->assertSessionHasNoErrors();

    expect(Tenant::query()->where('slug', 'grupo-ejemplo')->sole()->type)->toBe(TenantType::Union)
        ->and(Tenant::query()->where('slug', 'otra')->sole())->type->toBe(TenantType::Company)
        ->and(Tenant::query()->where('slug', 'grupo-ejemplo')->sole()->isUnion())->toBeTrue()
        ->and(Tenant::query()->where('slug', 'otra')->sole()->isUnion())->toBeFalse();
});

it('rejects an unknown type', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), typedCompanyInput(['type' => 'cooperative']))
        ->assertSessionHasErrors(['type' => 'El tipo de cuenta seleccionado no es válido.']);

    expect(Tenant::query()->count())->toBe(0);
});

it('never changes the type on update', function (): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);
    $company = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $union), typedCompanyInput(['rfc' => $union->rfc, 'slug' => $union->slug, 'type' => 'company']))
        ->assertSessionHasNoErrors();
    $this->actingAs(superadmin())
        ->put(route('companies.update', $company), typedCompanyInput(['rfc' => $company->rfc, 'slug' => $company->slug, 'type' => 'union']))
        ->assertSessionHasNoErrors();

    expect($union->refresh()->type)->toBe(TenantType::Union)
        ->and($company->refresh()->type)->toBe(TenantType::Company);
});

it('keeps existing companies as company and rejects a type outside the database check', function (): void {
    $tenant = Tenant::factory()->create();

    expect($tenant->refresh()->type)->toBe(TenantType::Company);

    expect(fn () => DB::table('tenants')->where('id', $tenant->id)->update(['type' => 'cooperative']))->toThrow(QueryException::class);
});

it('exposes the type in the contract', function (): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);

    expect(resolve(Companies::class)->enter($union->id)->type)->toBe(TenantType::Union)
        ->and(resolve(Companies::class)->current()->type)->toBe(TenantType::Union);
});

it('exposes tenant.type in the shared props', function (TenantType $type): void {
    $tenant = Tenant::factory()->create(['type' => $type->value]);

    $this->actingAs(memberOf($tenant, TenantRole::Owner))
        ->get(route('tenant.employees.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.type', $type->value));
})->with([TenantType::Company, TenantType::Union]);

it('hands a union only the hr role', function (): void {
    expect(TenantType::Union->assignableRoles())->toBe([TenantRole::Hr])
        ->and(TenantType::Company->assignableRoles())->toBe([TenantRole::Hr, TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);
});

it('refuses to invite a viewer or a committee member to a union', function (TenantRole $role): void {
    Notification::fake();
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);
    $owner = memberOf($union, TenantRole::Owner);

    $this->actingAs($owner)
        ->post(route('tenant.members.invitations.store', $union), ['name' => 'Vera', 'email' => 'vera@sindicato.mx', 'role' => $role->value])
        ->assertSessionHasErrors('role');

    expect(TenantInvitation::query()->where('email', 'vera@sindicato.mx')->exists())->toBeFalse();

    $this->actingAs($owner)
        ->post(route('tenant.members.invitations.store', $union), ['name' => 'Rita', 'email' => 'rita@sindicato.mx', 'role' => 'hr'])
        ->assertSessionHasNoErrors();
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('refuses to change a union member to a viewer or a committee member', function (TenantRole $role): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);
    $owner = memberOf($union, TenantRole::Owner);
    $member = TenantMembership::query()->where('user_id', memberOf($union, TenantRole::Hr)->id)->sole();

    $this->actingAs($owner)
        ->put(route('tenant.members.update', [$union, $member->id]), ['role' => $role->value])
        ->assertSessionHasErrors('role');

    expect(resolve(App\Modules\Tenancy\Support\CompanyRoles::class)->of($member->user()->sole(), $union))->toBe(TenantRole::Hr);
})->with([TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('still lets a company invite and change to every role', function (TenantRole $role): void {
    Notification::fake();
    $company = Tenant::factory()->create();
    $owner = memberOf($company, TenantRole::Owner);

    $this->actingAs($owner)
        ->post(route('tenant.members.invitations.store', $company), ['name' => 'Vera', 'email' => 'vera@acme.mx', 'role' => $role->value])
        ->assertSessionHasNoErrors();
})->with([TenantRole::Hr, TenantRole::Viewer, TenantRole::Committee, TenantRole::Complaints]);

it('guards the membership actions themselves, whatever calls them', function (): void {
    Notification::fake();
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);
    $owner = memberOf($union, TenantRole::Owner);
    $member = TenantMembership::query()->where('user_id', memberOf($union, TenantRole::Hr)->id)->sole();

    expect(fn () => resolve(InviteMember::class)->handle($union, new MemberInvitationData('Vera', 'vera@sindicato.mx', TenantRole::Viewer), $owner))
        ->toThrow(RoleNotAllowedInAccount::class)
        ->and(fn () => resolve(ChangeMemberRole::class)->handle($member, TenantRole::Committee, $owner))
        ->toThrow(RoleNotAllowedInAccount::class)
        ->and(TenantInvitation::query()->count())->toBe(0);
});

it('lists only the roles a union can hand out', function (): void {
    $union = Tenant::factory()->create(['type' => TenantType::Union->value]);

    $this->actingAs(memberOf($union, TenantRole::Owner))
        ->get(route('tenant.members.index', $union))
        ->assertInertia(fn (Assert $page) => $page->where('roles', [['value' => 'hr', 'label' => 'Ayuda con las rutas']]));
});
