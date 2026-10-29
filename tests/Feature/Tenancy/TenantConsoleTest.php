<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\ListTenants;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ListTenants::class);

it('lives at /admin/companies', function (): void {
    expect(route('companies.index', absolute: false))->toBe('/admin/companies');
});

it('sends guests to the login', function (): void {
    $this->get(route('companies.index'))->assertRedirect(route('login'));
});

it('answers 404 to anyone who is not a superadmin, so the console does not reveal itself', function (): void {
    Tenant::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('companies.index'))
        ->assertNotFound();
});

it('lists each company with its status for the superadmin', function (): void {
    $this->travelTo('2026-10-27 18:00 UTC');
    $tenant = Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC'))->create([
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'trade_name' => 'Grupo Ejemplo',
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
    ]);

    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/index')
            ->where('tenants.total', 1)
            ->where('tenants.data', [[
                'id' => $tenant->id,
                'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
                'trade_name' => 'Grupo Ejemplo',
                'rfc' => 'GEJ0101011A1',
                'slug' => 'grupo-ejemplo',
                'status' => 'trial',
                'status_label' => 'En prueba',
                'status_date' => 'Prueba hasta 3 nov 2026',
                'active_until' => null,
                'blocked' => false,
                'owner' => null,
                'invitation' => null,
            ]]));
});

it('describes the date of every status', function (Closure $company, string $status, ?string $statusDate, ?string $activeUntil, bool $blocked): void {
    $this->travelTo('2026-10-27 18:00 UTC');
    $company()->create();

    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.data.0.status', $status)
            ->where('tenants.data.0.status_date', $statusDate)
            ->where('tenants.data.0.active_until', $activeUntil)
            ->where('tenants.data.0.blocked', $blocked));
})->with([
    'not started' => [fn () => Tenant::factory(), 'not_started', null, null, false],
    'trial' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC')), 'trial', 'Prueba hasta 3 nov 2026', null, false],
    'trial ended' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-01 18:00', 'UTC')), 'trial_ended', 'Prueba terminó el 15 oct 2026', null, true],
    'active' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'America/Mexico_City')->endOfDay()), 'active', 'Activa hasta 31 dic 2026', '2026-12-31', false],
    'expired' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-09-30', 'America/Mexico_City')->endOfDay()), 'expired', 'Venció el 30 sep 2026', '2026-09-30', true],
    'deactivated' => [fn () => Tenant::factory()->deactivated(), 'deactivated', 'Desactivada el 27 oct 2026', null, true],
]);

it('shows the newest companies first', function (): void {
    Tenant::factory()->create(['trade_name' => 'Antigua', 'created_at' => now()->subDay()]);
    Tenant::factory()->create(['trade_name' => 'Reciente', 'created_at' => now()]);

    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.data.0.trade_name', 'Reciente')
            ->where('tenants.data.1.trade_name', 'Antigua'));
});

it('pages the list 25 at a time', function (): void {
    Tenant::factory()->count(26)->create();

    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 25)
            ->where('tenants.total', 26)
            ->where('tenants.prev_page_url', null)
            ->where('tenants.next_page_url', route('companies.index', ['page' => 2])));

    $this->actingAs(superadmin())
        ->get(route('companies.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.next_page_url', null));
});

it('renders the empty console when there are no companies yet', function (): void {
    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 0)
            ->where('tenants.total', 0));
});

it('reads the page in seven queries: the superadmin role, the permission cache (permissions and their roles), the count, the page, the owners and the open invitations', function (): void {
    Tenant::factory()->count(30)->create();
    // A fresh instance, as a real request gets: its roles are not loaded yet.
    $superadmin = superadmin()->fresh();

    $this->expectsDatabaseQueryCount(7);
    $this->actingAs($superadmin)->get(route('companies.index'))->assertOk();
});

it('shows each company owner and open invitation', function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
    $withOwner = Tenant::factory()->create(['trade_name' => 'Con owner']);
    TenantMembership::factory()->for($withOwner)->for(User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']))->create();
    $withInvitation = Tenant::factory()->create(['trade_name' => 'Con invitación']);
    $pending = TenantInvitation::factory()->for($withInvitation)->create(['name' => 'Beto', 'email' => 'beto@acme.mx', 'expires_at' => '2026-11-04 18:00 UTC']);
    $expired = Tenant::factory()->create(['trade_name' => 'Vencida']);
    $old = TenantInvitation::factory()->for($expired)->expired()->create(['name' => 'Carla', 'email' => 'carla@acme.mx']);
    $closed = Tenant::factory()->create(['trade_name' => 'Cerradas']);
    TenantInvitation::factory()->for($closed)->accepted()->create();
    TenantInvitation::factory()->for($closed)->cancelled()->create();

    $rows = collect($this->actingAs(superadmin())->get(route('companies.index'))->viewData('page')['props']['tenants']['data'])
        ->keyBy('trade_name');

    expect($rows['Con owner']['owner'])->toBe(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx'])
        ->and($rows['Con owner']['invitation'])->toBeNull()
        ->and($rows['Con invitación']['owner'])->toBeNull()
        ->and($rows['Con invitación']['invitation'])->toBe([
            'id' => $pending->id,
            'name' => 'Beto',
            'email' => 'beto@acme.mx',
            'status' => 'pending',
            'status_label' => 'Invitación enviada',
            'expires_on' => '4 nov 2026',
        ])
        ->and($rows['Vencida']['invitation'])->toMatchArray(['id' => $old->id, 'status' => 'expired', 'status_label' => 'Vencida'])
        ->and($rows['Cerradas']['invitation'])->toBeNull();
});

it('lists companies with a fixed number of queries', function (): void {
    $withOwner = Tenant::factory()->create();
    TenantMembership::factory()->for($withOwner)->create();
    TenantInvitation::factory()->for(Tenant::factory()->create())->create();
    Tenant::factory()->create();
    $superadmin = superadmin()->fresh();

    // The superadmin role, the permission cache (permissions and their roles), the count, the page, the owners, their
    // users and the open invitations.
    $this->expectsDatabaseQueryCount(8);
    $this->actingAs($superadmin)->get(route('companies.index'))->assertOk();
});
