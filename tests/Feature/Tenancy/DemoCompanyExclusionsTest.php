<?php

declare(strict_types=1);

use App\Http\Impersonation;
use App\Modules\Billing\Actions\RenewSubscriptions;
use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-12-01 10:00:00'));

it('does not renew or bill the demo', function (): void {
    [$tenant] = statementCompany();
    $tenant->forceFill(['is_demo' => true])->save();

    $summary = resolve(RenewSubscriptions::class)->handle();

    expect(Statement::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse()
        ->and($summary->drafted)->toBe(0)
        ->and($summary->skipped)->toBe([]);
});

it('leaves the demo out of the company counts and revenue', function (): void {
    Tenant::factory()->create();
    $demo = Tenant::factory()->demo()->activeUntil(now()->addMonth())->create();
    Subscription::factory()->plan(Plan::Cumplimiento)->create(['tenant_id' => $demo->id, 'seats' => 30, 'centers' => 1]);

    $this->actingAs(superadmin())->get(route('overview.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companies.all', 1)
            ->where('revenueCents', 0)
            ->where('inactive', 0));

    $this->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 1));
});

it('lets the superadmin see Compensation and Humi while impersonating the demo', function (): void {
    $demo = Tenant::factory()->demo()->create();
    ownerOf($demo);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $demo))->assertRedirect();

    $this->get(route('tenant.compensation.index', $demo))->assertOk();
    $this->getJson("/admin/{$demo->slug}/assistant/conversations")->assertOk();
});

it('enters the demo as HR', function (): void {
    $demo = Tenant::factory()->demo()->create();
    ownerOf($demo);
    $staffHr = superadmin();
    App\Modules\Tenancy\Models\TenantMembership::factory()->for($demo)->for($staffHr)->role(TenantRole::Hr)->create();
    $hr = memberOf($demo, TenantRole::Hr);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $demo), ['as' => 'hr'])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($hr);
    expect(session(Impersonation::TENANT_KEY))->toBe($demo->id);
});

it('answers 404 when the demo has no HR member to enter as', function (): void {
    $demo = Tenant::factory()->demo()->create();
    ownerOf($demo);
    $superadmin = superadmin();

    $this->actingAs($superadmin)->post(route('companies.impersonate', $demo), ['as' => 'hr'])->assertNotFound();

    $this->assertAuthenticatedAs($superadmin);
});

it('refuses as=hr in a real company', function (): void {
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);
    memberOf($tenant, TenantRole::Hr);
    $superadmin = superadmin();

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant), ['as' => 'hr'])->assertNotFound();

    $this->assertAuthenticatedAs($superadmin);
});

it('accepts only owner or hr', function (): void {
    $demo = Tenant::factory()->demo()->create();
    ownerOf($demo);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $demo), ['as' => 'viewer'])->assertSessionHasErrors('as');
});

it('still enters as the owner by default', function (): void {
    $demo = Tenant::factory()->demo()->create();
    $owner = ownerOf($demo);
    memberOf($demo, TenantRole::Hr);

    $this->actingAs(superadmin())->post(route('companies.impersonate', $demo))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($owner);
});
