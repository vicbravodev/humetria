<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Sso\Actions\SaveSsoConnection;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SaveSsoConnection::class);

function ssoOf(Tenant $tenant): SsoConnection
{
    return SsoConnection::query()->where('tenant_id', $tenant->id)->firstOrFail();
}

it('lives at /admin/{company}/sso', function (): void {
    expect(route('tenant.sso.show', 'acme', absolute: false))->toBe('/admin/acme/sso');
});

it('shows the owner an empty setup without any secret', function (): void {
    [$tenant, $owner] = ssoCompany();

    $this->actingAs($owner)->get(route('tenant.sso.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('sso/settings')
        ->where('connection', null)
        ->where('domains', [])
        ->where('redirect_uri', route('sso.callback')));
});

it('keeps the settings for the owner: anyone else is refused', function (TenantRole $role): void {
    [$tenant] = ssoCompany();

    $this->actingAs(memberOf($tenant, $role))->get(route('tenant.sso.show', $tenant))->assertForbidden();
    $this->actingAs(memberOf($tenant, $role))->put(route('tenant.sso.update', $tenant), ssoForm())->assertForbidden();
    expect(SsoConnection::query()->count())->toBe(0);
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 to a company without single sign-on in its plan', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $owner = memberOf($tenant, TenantRole::Owner);

    $this->actingAs($owner)->get(route('tenant.sso.show', $tenant))->assertNotFound();
    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm())->assertNotFound();
    expect(SsoConnection::query()->count())->toBe(0);
});

it('answers 404 to a member of another company, not 403', function (): void {
    [$tenant] = ssoCompany('acme');
    [$other, $stranger] = ssoCompany('beta');

    $this->actingAs($stranger)->get(route('tenant.sso.show', $tenant))->assertNotFound();
    $this->actingAs($stranger)->put(route('tenant.sso.update', $tenant), ssoForm())->assertNotFound();
    expect(SsoConnection::query()->count())->toBe(0)->and($other->slug)->toBe('beta');
});

it('saves the connection, stores the secret encrypted and never sends it back', function (): void {
    [$tenant, $owner] = ssoCompany();

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm())->assertRedirect(route('tenant.sso.show', $tenant))->assertSessionHasNoErrors();

    $stored = DB::table('sso_connections')->where('tenant_id', $tenant->id)->first();
    expect($stored)->not->toBeNull()
        ->and($stored?->issuer)->toBe('https://idp.acme.test')
        ->and($stored?->client_secret)->not->toContain('super-secret-value')
        ->and(ssoOf($tenant)->client_secret)->toBe('super-secret-value')
        ->and(ssoOf($tenant)->enabled)->toBeFalse();

    $this->actingAs($owner)->get(route('tenant.sso.show', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('connection.issuer', 'https://idp.acme.test')
        ->where('connection.has_secret', true)
        ->missing('connection.client_secret')
        ->where('domains.0.domain', 'acme.com.mx')
        ->where('domains.0.verified', false)
        ->where('domains.0.record_name', '_humetria-sso.acme.com.mx')
        ->where('domains.0.record_value', fn (string $value) => str_starts_with($value, 'humetria-sso=')));
});

it('keeps the secret when the form leaves it blank', function (): void {
    [$tenant, $owner] = ssoCompany();
    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm());

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm(['client_secret' => '', 'client_id' => 'otro']))->assertSessionHasNoErrors();

    expect(ssoOf($tenant)->client_secret)->toBe('super-secret-value')->and(ssoOf($tenant)->client_id)->toBe('otro');
});

it('refuses a missing secret the first time and a bad issuer or domain always', function (array $overrides, string $field): void {
    [$tenant, $owner] = ssoCompany();

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm($overrides))->assertSessionHasErrors($field);
    expect(SsoConnection::query()->count())->toBe(0);
})->with([
    'no secret yet' => [['client_secret' => ''], 'client_secret'],
    'http issuer' => [['issuer' => 'http://idp.acme.test'], 'issuer'],
    'a bare host as issuer' => [['issuer' => 'idp.acme.test'], 'issuer'],
    'localhost issuer' => [['issuer' => 'https://localhost'], 'issuer'],
    'an IP as issuer' => [['issuer' => 'https://10.0.0.5'], 'issuer'],
    'no client id' => [['client_id' => ''], 'client_id'],
    'no domain' => [['domains' => []], 'domains'],
    'a domain with a path' => [['domains' => ['acme.mx/hr']], 'domains.0'],
    'an address instead of a domain' => [['domains' => ['rh@acme.mx']], 'domains.0'],
    'repeated domains' => [['domains' => ['acme.mx', 'ACME.mx']], 'domains.1'],
]);

it('normalizes the domains and keeps the proof of those that stay', function (): void {
    [$tenant, $owner] = ssoCompany();
    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm(['domains' => ['  ACME.mx ', 'acme.com.mx']]));
    SsoDomain::query()->where('tenant_id', $tenant->id)->where('domain', 'acme.mx')->update(['verified_at' => now()]);

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm(['domains' => ['acme.mx', 'nuevo.mx']]));

    $domains = SsoDomain::query()->where('tenant_id', $tenant->id)->orderBy('domain')->get();
    expect($domains->pluck('domain')->all())->toBe(['acme.mx', 'nuevo.mx'])
        ->and($domains->firstWhere('domain', 'acme.mx')?->verified_at)->not->toBeNull()
        ->and($domains->firstWhere('domain', 'nuevo.mx')?->verified_at)->toBeNull();
});

it('records the change in the audit trail and the log, with no secret', function (): void {
    Log::spy();
    [$tenant, $owner] = ssoCompany();

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm());

    $entry = AuditEntry::query()->where('event', 'sso.configured')->sole();
    expect($entry->tenant_id)->toBe($tenant->id)
        ->and(auditContent($entry))->not->toContain('super-secret-value')
        ->and(auditContent($entry))->toContain('acme.mx');
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'sso.configured' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'super-secret-value'))->once();
});
