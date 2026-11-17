<?php

declare(strict_types=1);

use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;

/** A company that requires single sign-on of everyone but its owner. */
function ssoEnforced(string $slug = 'acme', string $domain = 'acme.mx'): array
{
    [$tenant, $owner, $member] = ssoReady($slug, $domain);
    SsoConnection::query()->where('tenant_id', $tenant->id)->update(['enforced' => true]);

    return [$tenant, $owner, $member];
}

it('sends a member who came in with a password to the single sign-on of the company', function (): void {
    [$tenant, , $member] = ssoEnforced();

    $this->actingAs($member)->get(route('tenant.dashboard', $tenant))->assertRedirect(route('sso.start', 'acme'));
    $this->actingAs($member)->get(route('tenant.members.index', $tenant))->assertRedirect(route('sso.start', 'acme'));
});

it('does it for a page that loads by script too, by telling Inertia where to go', function (): void {
    [$tenant, , $member] = ssoEnforced();

    $this->actingAs($member)->get(route('tenant.dashboard', $tenant), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request())])->assertStatus(409)->assertHeader('X-Inertia-Location', route('sso.start', 'acme'));
});

it('lets in the member who signed in through the company provider', function (): void {
    [$tenant, , $member] = ssoEnforced();

    $this->actingAs($member)->withSession(['sso.user_id' => $member->id, 'sso.tenants' => [$tenant->id]])->get(route('tenant.dashboard', $tenant))->assertOk();
});

it('does not take the mark of another person or of another company', function (): void {
    [$tenant, $owner, $member] = ssoEnforced();
    [$other] = ssoEnforced('beta', 'beta.mx');
    TenantMembership::factory()->for($other)->for($member)->role(TenantRole::Hr)->create();

    $this->actingAs($member)->withSession(['sso.user_id' => $owner->id, 'sso.tenants' => [$tenant->id]])->get(route('tenant.dashboard', $tenant))->assertRedirect(route('sso.start', 'acme'));
    $this->actingAs($member)->withSession(['sso.user_id' => $member->id, 'sso.tenants' => [$tenant->id]])->get(route('tenant.dashboard', $other))->assertRedirect(route('sso.start', 'beta'));
});

it('keeps the way out of the owner: the password still opens the company', function (): void {
    [$tenant, $owner] = ssoEnforced();

    $this->actingAs($owner)->get(route('tenant.dashboard', $tenant))->assertOk();
});

it('asks nothing of anyone while it is not enforced', function (): void {
    [$tenant, , $member] = ssoReady();

    $this->actingAs($member)->get(route('tenant.dashboard', $tenant))->assertOk();
});

it('stops enforcing it when the plan no longer has the module, since nobody could sign in', function (): void {
    [$tenant, , $member] = ssoEnforced();
    TenantModule::query()->where('tenant_id', $tenant->id)->delete();
    resolve(App\Modules\Tenancy\Support\ModuleStates::class)->forget();

    $this->actingAs($member)->get(route('tenant.dashboard', $tenant))->assertOk();
});

it('leaves the companies that do not enforce it alone', function (): void {
    [$tenant, , $member] = ssoEnforced();
    [$other] = ssoReady('beta', 'beta.mx');
    TenantMembership::factory()->for($other)->for($member)->role(TenantRole::Hr)->create();

    $this->actingAs($member)->get(route('tenant.dashboard', $other))->assertOk();
    expect($tenant->slug)->toBe('acme');
});

it('remembers each company the person signed in to, for that person only', function (): void {
    [$tenant, , $member] = ssoReady();
    idpAnswers();

    ssoCallback($tenant, session: ['sso.user_id' => 999, 'sso.tenants' => ['otra-empresa']])->assertRedirect();

    expect(session('sso.user_id'))->toBe($member->id)->and(session('sso.tenants'))->toBe([$tenant->id]);
});
