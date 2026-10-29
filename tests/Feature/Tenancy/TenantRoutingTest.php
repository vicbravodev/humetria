<?php

declare(strict_types=1);

use App\Actions\RecordAudit;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Http\Controllers\TenantDashboardController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ResolveTenant::class, TenantDashboardController::class);

it('lives at /admin/{slug}', function (): void {
    expect(route('tenant.dashboard', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme');
});

it('shows the company panel to its owner with the company as a shared prop', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme Industrial']);

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.slug', 'acme')
            ->where('tenant.trade_name', 'Acme Industrial')
            ->where('tenant.status', 'not_started')
            ->where('tenant.blocked', false)
            ->where('tenant.trial_days_left', null));
});

it('flags a blocked company in the shared prop', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.status', 'deactivated')
            ->where('tenant.blocked', true));
});

it('answers 404 to a member of another company', function (): void {
    $mine = Tenant::factory()->create();
    $other = Tenant::factory()->create(['slug' => 'other']);

    $this->actingAs(ownerOf($mine))
        ->get(route('tenant.dashboard', $other))
        ->assertNotFound();
});

it('answers 404 to a slug that does not exist', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin/does-not-exist')
        ->assertNotFound();
});

it('answers 404 to a superadmin who is not a member', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->get(route('tenant.dashboard', $tenant))
        ->assertNotFound();
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->get(route('tenant.dashboard', $tenant))->assertRedirect(route('login'));
});

it('records the company on audit entries written inside it', function (): void {
    $tenant = Tenant::factory()->create();
    Route::middleware(['web', 'auth', ResolveTenant::class])
        ->get('admin/{tenant:slug}/audit-probe', function (): string {
            resolve(RecordAudit::class)->handle('probe.visited');

            return 'ok';
        })->name('tenant.audit-probe');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs(ownerOf($tenant))->get(route('tenant.audit-probe', $tenant))->assertOk();

    expect(AuditEntry::query()->where('event', 'probe.visited')->sole()->tenant_id)->toBe($tenant->id);
});

it('shares the companies of the user ordered by name, and never somebody else\'s', function (): void {
    $zeta = Tenant::factory()->create(['slug' => 'zeta', 'trade_name' => 'Zeta']);
    $alfa = Tenant::factory()->create(['slug' => 'alfa', 'trade_name' => 'Alfa']);
    $foreign = Tenant::factory()->create(['slug' => 'foreign']);
    $user = ownerOf($zeta);
    TenantMembership::factory()->for($alfa)->for($user)->create();

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $zeta))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants', [
                ['slug' => 'alfa', 'trade_name' => 'Alfa'],
                ['slug' => 'zeta', 'trade_name' => 'Zeta'],
            ])
            ->where('tenants', fn ($tenants): bool => ! collect($tenants)->contains('slug', $foreign->slug)));
});

it('does not shadow the fixed pages under /admin', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->actingAs(User::factory()->create())->get('/admin/settings/appearance')->assertOk();
    $this->actingAs(superadmin())->get('/admin/companies')->assertOk();
    auth()->logout();
    $this->get('/admin/login')->assertOk();
});
