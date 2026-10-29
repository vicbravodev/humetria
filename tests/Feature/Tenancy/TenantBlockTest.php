<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(BlockSuspendedTenant::class);

$blocked = [
    'trial ended' => fn (): Tenant => Tenant::factory()->inTrial(now()->subDays(15))->create(),
    'expired' => fn (): Tenant => Tenant::factory()->activeUntil(now()->subDay())->create(),
    'deactivated' => fn (): Tenant => Tenant::factory()->deactivated()->create(),
];

$open = [
    'not started' => fn (): Tenant => Tenant::factory()->create(),
    'in trial' => fn (): Tenant => Tenant::factory()->inTrial(now()->subDays(3))->create(),
    'active' => fn (): Tenant => Tenant::factory()->activeUntil(now()->addMonth())->create(),
];

beforeEach(function (): void {
    $this->effects = 0;
    $effects = &$this->effects;
    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class])
        ->group(function () use (&$effects): void {
            Route::post('admin/{tenant:slug}/_probe', function () use (&$effects): string {
                $effects++;

                return 'ok';
            })->name('tenant.block-probe');
        });
    Route::getRoutes()->refreshNameLookups();
});

it('puts the blocked page, with no props of its own, where the panel would be', function (Closure $make): void {
    $tenant = $make();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/blocked')
            ->where('tenant.blocked', true)
            ->has('tenants', 1)
            // Only what every page shares: nothing from the dashboard rides along.
            ->where('tenant.slug', $tenant->slug));
})->with($blocked);

it('carries nothing but the shared props to the blocked page', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $props = $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->viewData('page')['props'];

    expect(array_keys($props))->toEqualCanonicalizing(['name', 'auth', 'impersonator', 'tenant', 'tenants', 'sidebarOpen', 'errors']);
});

it('answers HEAD with the blocked page too', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $this->actingAs(ownerOf($tenant))
        ->call('HEAD', route('tenant.dashboard', $tenant, absolute: false))
        ->assertOk();
});

it('rejects with 423 any change to a blocked company, leaving no effect and a log', function (Closure $make): void {
    $tenant = $make();
    Log::spy();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.block-probe', $tenant))
        ->assertStatus(423);

    expect($this->effects)->toBe(0);
    Log::shouldHaveReceived('warning')->with('tenant.blocked_request_rejected', ['tenant_id' => $tenant->id])->once();
})->with($blocked);

it('lets a company that is not blocked work as usual', function (Closure $make): void {
    $tenant = $make();
    $user = ownerOf($tenant);

    $this->actingAs($user)->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    $this->actingAs($user)->post(route('tenant.block-probe', $tenant))->assertOk();

    expect($this->effects)->toBe(1);
})->with($open);

it('lets an impersonated session through to a blocked company', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();
    $user = ownerOf($tenant);

    $this->actingAs($user)
        ->withSession([Impersonation::SESSION_KEY => superadmin()->id, Impersonation::IMPERSONATED_KEY => $user->id])
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    $this->post(route('tenant.block-probe', $tenant))->assertOk();
});

it('still hides a blocked company from someone who is not a member', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->get(route('tenant.dashboard', $tenant))
        ->assertNotFound();
});

it('keeps the other companies of the person reachable', function (): void {
    $blocked = Tenant::factory()->deactivated()->create();
    $open = Tenant::factory()->create();
    $user = ownerOf($blocked);
    TenantMembership::factory()->for($open)->for($user)->create();

    $this->actingAs($user)->get(route('tenant.dashboard', $open))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('tenants', 2));
});
