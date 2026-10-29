<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(BlockSuspendedTenant::class);

it('gives an impersonating superadmin the real page of an expired company, told it is blocked', function (): void {
    $effects = 0;
    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class])
        ->post('admin/{tenant:slug}/_probe', function () use (&$effects): string {
            $effects++;

            return 'ok';
        })->name('tenant.impersonated-probe');
    Route::getRoutes()->refreshNameLookups();
    $superadmin = superadmin();
    $tenant = Tenant::factory()->activeUntil(now()->subDay())->create();
    ownerOf($tenant);

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant));

    $this->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.blocked', true)
            ->where('impersonator', $superadmin->name));
    $this->post(route('tenant.impersonated-probe', $tenant))->assertOk();

    expect($effects)->toBe(1);
});

it('never tells a member of a blocked company they are impersonated', function (): void {
    $tenant = Tenant::factory()->activeUntil(now()->subDay())->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/blocked')
            ->where('tenant.blocked', true)
            ->where('impersonator', null));
});
