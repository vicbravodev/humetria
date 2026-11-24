<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Support\AssistantScopeFactory;
use App\Modules\Assistant\Support\Readers\AccessReader;
use App\Modules\Assistant\Tools\GetComplianceOverview;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\GrantMemberPermissions;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyAccessReader;
use App\Modules\Tenancy\Support\CompanyRoles;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

mutates(AccessReader::class, CompanyAccessReader::class);

beforeEach(function (): void {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->owner = ownerOf($this->a);
});

/** A role of the company's own, made of `$permissions`. */
function accessOwnRole(Tenant $tenant, User $by, string $label, array $permissions = ['view-dashboard']): Role
{
    return app(CreateCompanyRole::class)->handle($tenant, $label, $permissions, null, $by);
}

/** A person of the company who holds `$role`, named so a privacy assertion can look for them. */
function accessHolderOf(Tenant $tenant, Role $role, string $name = 'Rosalinda Pérez'): User
{
    $person = User::factory()->create(['name' => $name]);
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    return $person;
}

/** The scope of `$user` in the company. */
function accessScopeOf(Tenant $tenant, User $user): AssistantScope
{
    workIn($tenant);

    return resolve(AssistantScopeFactory::class)->make($user);
}

/** The roles-and-permissions line of the overview for `$scope`. */
function accessLine(AssistantScope $scope): array
{
    return array_values(array_filter(toolCard(GetComplianceOverview::class, $scope)['rows'], fn (array $row): bool => $row['key'] === 'access'))[0];
}

it('tells the owner how many own roles the company has of the maximum, how many people hold each role and how many have extras', function (): void {
    $audit = accessOwnRole($this->a, $this->owner, 'Auditoría');
    accessHolderOf($this->a, $audit, 'Persona Uno');
    accessHolderOf($this->a, $audit, 'Persona Dos');
    accessOwnRole($this->a, $this->owner, 'Sin nadie');
    $hr = memberOf($this->a, TenantRole::Hr);
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $this->a->id)->where('user_id', $hr->id)->sole(), ['view-climate'], $this->owner);

    $line = accessLine(accessScopeOf($this->a, $this->owner));

    expect($line)->toMatchArray(['label' => 'Roles y permisos', 'state' => 'up_to_date', 'stateLabel' => 'Al día', 'link' => "/admin/{$this->a->slug}/roles"])
        ->and($line['summary'])->toBe('Tienes 2 de 20 roles propios. Personas por rol: Administra todo, 1; Ayuda con las rutas, 1; Auditoría, 2. 1 persona tiene permisos además de su rol. Tú administras todo lo que incluye tu plan, también los roles y las personas.');
});

it('tells the owner when the company has no roles of its own and nobody with extras', function (): void {
    expect(accessLine(accessScopeOf($this->a, $this->owner))['summary'])
        ->toBe('Aún no tienes roles propios (máximo 20). Personas por rol: Administra todo, 1. Nadie tiene permisos además de su rol. Tú administras todo lo que incluye tu plan, también los roles y las personas.');
});

it('tells the owner when the company reached the maximum of own roles', function (): void {
    config(['tenancy.max_custom_roles' => 2]);
    accessOwnRole($this->a, $this->owner, 'Uno');
    accessOwnRole($this->a, $this->owner, 'Dos');

    expect(accessLine(accessScopeOf($this->a, $this->owner))['summary'])->toStartWith('Tienes 2 de 2 roles propios, el máximo: para crear otro, borra uno que no uses.');
});

it('counts nothing of another company', function (): void {
    $ownerOfB = ownerOf($this->b);
    $foreign = accessOwnRole($this->b, $ownerOfB, 'Ajeno');
    accessHolderOf($this->b, $foreign);
    $hrOfB = memberOf($this->b, TenantRole::Hr);
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $this->b->id)->where('user_id', $hrOfB->id)->sole(), ['view-climate'], $ownerOfB);

    expect(accessLine(accessScopeOf($this->a, $this->owner))['summary'])->toStartWith('Aún no tienes roles propios (máximo 20). Personas por rol: Administra todo, 1. Nadie tiene');
});

it('tells anyone else only what they themselves can do, area by area, with what the role and their extras give', function (): void {
    $person = accessHolderOf($this->a, accessOwnRole($this->a, $this->owner, 'Auditoría', ['view-dashboard', 'view-climate', 'view-nom035-route', 'view-interventions', 'view-disclosure']));
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $this->a->id)->where('user_id', $person->id)->sole(), ['manage-climate', 'view-trauma-screenings'], $this->owner);

    $line = accessLine(accessScopeOf($this->a, $person));

    expect($line['link'])->toBe("/admin/{$this->a->slug}")
        ->and($line['summary'])->toStartWith('Tu acceso: Panel: Ver; NOM-035: Ver, y ve los tamizajes de la Guía I; Clima laboral: Administrar.')
        ->and($line['summary'])->toContain('Sin acceso: Centros de trabajo y empleados, ')
        ->and($line['summary'])->toContain('Pulso')
        ->and($line['summary'])->not->toContain('roles propios')
        ->and($line['summary'])->not->toContain('Auditoría');
});

it('never tells anyone but the owner how many people hold each role nor how many have extras', function (): void {
    $audit = accessOwnRole($this->a, $this->owner, 'Auditoría');
    accessHolderOf($this->a, $audit, 'Persona Uno');
    $hr = memberOf($this->a, TenantRole::Hr);
    app(GrantMemberPermissions::class)->handle(TenantMembership::query()->where('tenant_id', $this->a->id)->where('user_id', $hr->id)->sole(), ['view-climate'], $this->owner);

    foreach ([$hr, memberOf($this->a, TenantRole::Viewer)] as $person) {
        expect(accessLine(accessScopeOf($this->a, $person))['summary'])
            ->not->toContain('Personas por rol')
            ->not->toContain('además de su rol')
            ->not->toContain('Nadie tiene permisos');
    }
});

it('leaves out the areas of the tools the plan does not include', function (): void {
    resolve(CompanyModules::class)->syncPlan($this->a->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));
    $person = accessHolderOf($this->a, accessOwnRole($this->a, $this->owner, 'Auditoría', ['view-dashboard', 'view-nom035-route', 'view-interventions', 'view-disclosure']));

    $summary = accessLine(accessScopeOf($this->a, $person))['summary'];

    expect($summary)->toContain('NOM-035: Ver')->not->toContain('Clima laboral')->not->toContain('Pulso')->not->toContain('Ley Silla');
});

it('never carries a name nor an email of a person', function (): void {
    $audit = accessOwnRole($this->a, $this->owner, 'Auditoría');
    $person = accessHolderOf($this->a, $audit, 'Rosalinda Pérez');
    $hr = memberOf($this->a, TenantRole::Hr);

    $json = json_encode([toolCard(GetComplianceOverview::class, accessScopeOf($this->a, $this->owner)), toolCard(GetComplianceOverview::class, accessScopeOf($this->a, $hr))], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    expect($json)->not->toContain('Rosalinda')->not->toContain($person->email)
        ->not->toContain($this->owner->name)->not->toContain($this->owner->email)
        ->not->toContain($hr->email);
});

it('tells the model the overview also answers who can do what', function (): void {
    $description = (string) resolve(GetComplianceOverview::class, ['scope' => accessScopeOf($this->a, $this->owner)])->description();

    expect($description)->toContain('roles y permisos')->toContain('qué puede hacer quien pregunta');
});
