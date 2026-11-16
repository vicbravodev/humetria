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
    'suspended' => fn (): Tenant => Tenant::factory()->suspended()->create(),
    'archived' => fn (): Tenant => Tenant::factory()->archived()->create(),
];

// What a company falls to when its trial or term ends, or when a person says so: people read, nothing is changed.
$readOnly = [
    'trial ended' => fn (): Tenant => Tenant::factory()->inTrial(now()->subDays(15))->create(),
    'expired' => fn (): Tenant => Tenant::factory()->activeUntil(now()->subDay())->create(),
    'read only' => fn (): Tenant => Tenant::factory()->readOnly()->create(),
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
            ->has('tenants.data', 1)
            // Only what every page shares: nothing from the dashboard rides along.
            ->where('tenant.slug', $tenant->slug));
})->with($blocked);

it('carries nothing but the shared props to the blocked page', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $props = $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->viewData('page')['props'];

    expect(array_keys($props))->toEqualCanonicalizing(['name', 'support', 'auth', 'impersonator', 'tenant', 'tenants', 'inCourse', 'firstSteps', 'sidebarOpen', 'errors']);
});

it('answers HEAD with the blocked page too', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

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

it('lets the members of a read-only company read its pages, told that it is read only and not blocked', function (Closure $make): void {
    $tenant = $make();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.blocked', false)
            ->where('tenant.read_only', true));
})->with($readOnly);

it('turns off every writing permission of a read-only company, keeps the ones that read, and keeps the full set for the menu', function (Closure $make): void {
    $tenant = $make();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props']['tenant'];

            foreach ($props['can'] as $key => $allowed) {
                $writes = str_starts_with($key, 'manage_') || in_array($key, ['assess_seating', 'review_workplaces', 'handle_complaints'], true);

                expect($allowed)->toBe($writes ? false : $props['permissions'][$key], $key);
            }

            // What the role allows is still there for the menu: a read-only company reads and exports everything.
            expect($props['permissions']['manage_organization'])->toBeTrue()->and($props['can']['view_attendance'])->toBeTrue();
        });
})->with($readOnly);

it('leaves the writing permissions of a company that works as usual as its role gives them', function (Closure $make): void {
    $tenant = $make();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.manage_organization', true)
            ->where('tenant.can.manage_campaigns', true)
            ->where('tenant.permissions.manage_organization', true));
})->with($open);

it('rejects with 423 any change to a read-only company, leaving no effect and a log', function (Closure $make): void {
    $tenant = $make();
    Log::spy();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.block-probe', $tenant))
        ->assertStatus(423);

    expect($this->effects)->toBe(0);
    Log::shouldHaveReceived('warning')->with('tenant.read_only_request_rejected', ['tenant_id' => $tenant->id])->once();
})->with($readOnly);

it('does not say a company is read only while it works as usual', function (Closure $make): void {
    $tenant = $make();

    $this->actingAs(ownerOf($tenant))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.read_only', false)->where('tenant.blocked', false));
})->with($open);

it('lets a company that is not blocked work as usual', function (Closure $make): void {
    $tenant = $make();
    $user = ownerOf($tenant);

    $this->actingAs($user)->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    $this->actingAs($user)->post(route('tenant.block-probe', $tenant))->assertOk();

    expect($this->effects)->toBe(1);
})->with($open);

it('lets an impersonated session see a blocked company, never change it', function (): void {
    $tenant = Tenant::factory()->suspended()->create();
    $user = ownerOf($tenant);

    $this->actingAs($user)
        ->withSession([Impersonation::SESSION_KEY => superadmin()->id, Impersonation::IMPERSONATED_KEY => $user->id])
        ->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
    $this->post(route('tenant.block-probe', $tenant))->assertStatus(423);
});

it('still hides a blocked company from someone who is not a member', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->get(route('tenant.dashboard', $tenant))
        ->assertNotFound();
});

it('keeps the other companies of the person reachable', function (): void {
    $blocked = Tenant::factory()->suspended()->create();
    $open = Tenant::factory()->create();
    $user = ownerOf($blocked);
    TenantMembership::factory()->for($open)->for($user)->create();

    $this->actingAs($user)->get(route('tenant.dashboard', $open))
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->has('tenants', 2));
});

it('shares the address to write to Humetria, and null while none is configured, even if the setting is empty', function (?string $configured, ?string $shared): void {
    config(['app.support_email' => $configured]);
    $tenant = Tenant::factory()->activeUntil(now()->addMonth())->create();

    $this->actingAs(ownerOf($tenant))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('support.email', $shared));
})->with([
    'configured' => ['soporte@humetria.mx', 'soporte@humetria.mx'],
    'not configured' => [null, null],
    'empty' => ['', null],
]);

it('shares the reason a person gave for suspending or archiving the company, so the notice can say it', function (): void {
    $tenant = Tenant::factory()->suspended('Falta de pago de octubre')->create();

    $this->actingAs(ownerOf($tenant))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.restriction_reason', 'Falta de pago de octubre'));
});

it('shares no reason for a company that is not blocked', function (): void {
    $tenant = Tenant::factory()->readOnly('Auditoría')->create();

    $this->actingAs(ownerOf($tenant))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.restriction_reason', null));
});
