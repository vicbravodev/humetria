<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Sso\Actions\SetSsoState;
use App\Modules\Sso\Actions\VerifySsoDomain;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(SetSsoState::class, VerifySsoDomain::class);

/** A company with a connection and a domain, not proven yet. */
function ssoSetUp(string $slug = 'acme', bool $verified = false, string $name = 'acme.mx'): array
{
    [$tenant, $owner] = ssoCompany($slug);
    SsoConnection::factory()->create(['tenant_id' => $tenant->id]);
    $domain = SsoDomain::factory()->create(['tenant_id' => $tenant->id, 'domain' => $name, 'verified_at' => $verified ? now() : null]);

    return [$tenant, $owner, $domain];
}

it('verifies a domain whose DNS carries the record', function (): void {
    Log::spy();
    [$tenant, $owner, $domain] = ssoSetUp();
    dnsProves('acme.mx');

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $domain]))->assertRedirect(route('tenant.sso.show', $tenant))->assertSessionHasNoErrors();

    expect($domain->fresh()?->verified_at)->not->toBeNull()
        ->and(AuditEntry::query()->where('event', 'sso.domain_verified')->where('tenant_id', $tenant->id)->count())->toBe(1);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'sso.domain_verified')->once();
});

it('leaves a domain unverified when the DNS does not carry the record', function (): void {
    [$tenant, $owner, $domain] = ssoSetUp();
    dnsProves();

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $domain]))->assertSessionHasErrors('domain');

    expect($domain->fresh()?->verified_at)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'sso.domain_verified')->count())->toBe(0);
});

it('does not verify a domain another company already proved', function (): void {
    ssoSetUp('acme', verified: true);
    [$tenant, $owner, $claim] = ssoSetUp('beta');
    dnsProves('acme.mx');

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $claim]))->assertSessionHasErrors('domain');

    expect($claim->fresh()?->verified_at)->toBeNull();
});

it('leaves a domain already proven as it is, without asking the DNS again', function (): void {
    [$tenant, $owner, $domain] = ssoSetUp(verified: true);
    $provenAt = $domain->verified_at;
    dnsProves();

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $domain]))->assertSessionHasNoErrors();

    expect($domain->fresh()?->verified_at?->equalTo($provenAt))->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'sso.domain_verified')->count())->toBe(0);
});

it('does not verify a domain another company proves in the same instant: the database keeps one', function (): void {
    [$rival] = ssoSetUp('acme');
    [$tenant, $owner, $claim] = ssoSetUp('beta');
    dnsProves('acme.mx');
    $won = false;
    // The rival proves it right after the check that nobody had, before this company writes its proof.
    DB::listen(function (QueryExecuted $query) use ($rival, &$won): void {
        if (! $won && str_starts_with($query->sql, 'select exists') && str_contains($query->sql, '"sso_domains"')) {
            $won = true;
            SsoDomain::query()->where('tenant_id', $rival->id)->update(['verified_at' => now()]);
        }
    });

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $claim]))->assertSessionHasErrors('domain');

    expect($claim->fresh()?->verified_at)->toBeNull()
        ->and(SsoDomain::query()->whereNotNull('verified_at')->pluck('tenant_id')->all())->toBe([$rival->id])
        ->and(AuditEntry::query()->where('event', 'sso.domain_verified')->count())->toBe(0);
});

it('answers 404 for the domain of another company', function (): void {
    [$tenant, $owner] = ssoSetUp('acme');
    [, , $foreign] = ssoSetUp('beta');
    dnsProves('acme.mx');

    $this->actingAs($owner)->post(route('tenant.sso.domains.verify', [$tenant, $foreign]))->assertNotFound();
    expect($foreign->fresh()?->verified_at)->toBeNull();
});

it('keeps verifying for the owner: HR is refused', function (): void {
    [$tenant, , $domain] = ssoSetUp();
    dnsProves('acme.mx');

    $this->actingAs(memberOf($tenant, TenantRole::Hr))->post(route('tenant.sso.domains.verify', [$tenant, $domain]))->assertForbidden();
    expect($domain->fresh()?->verified_at)->toBeNull();
});

it('turns single sign-on on once a domain is proven, and records it', function (): void {
    Log::spy();
    [$tenant, $owner] = ssoSetUp(verified: true);

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => false])->assertRedirect(route('tenant.sso.show', $tenant))->assertSessionHasNoErrors();

    expect(SsoConnection::query()->where('tenant_id', $tenant->id)->sole()->enabled)->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'sso.enabled')->where('tenant_id', $tenant->id)->count())->toBe(1);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event): bool => $event === 'sso.enabled')->once();
});

it('refuses to turn it on without a proven domain or without a connection', function (): void {
    [$tenant, $owner] = ssoSetUp();

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => false])->assertSessionHasErrors('enabled');

    [$bare, $bareOwner] = ssoCompany('beta');
    SsoDomain::factory()->verified()->create(['tenant_id' => $bare->id, 'domain' => 'beta.mx']);
    $this->actingAs($bareOwner)->put(route('tenant.sso.state', $bare), ['enabled' => true, 'enforced' => false])->assertSessionHasErrors('enabled');

    expect(SsoConnection::query()->where('enabled', true)->count())->toBe(0);
});

it('enforces it only while it is on, and lifts the enforcement when it is turned off', function (): void {
    [$tenant, $owner] = ssoSetUp(verified: true);

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => false, 'enforced' => true])->assertSessionHasErrors('enforced');

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => true])->assertSessionHasNoErrors();
    expect(SsoConnection::query()->where('tenant_id', $tenant->id)->sole()->enforced)->toBeTrue()
        ->and(AuditEntry::query()->where('event', 'sso.enforced')->count())->toBe(1);

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => false]);
    expect(AuditEntry::query()->where('event', 'sso.enforcement_lifted')->count())->toBe(1);

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => true]);
    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => false, 'enforced' => false]);
    $connection = SsoConnection::query()->where('tenant_id', $tenant->id)->sole();
    expect($connection->enabled)->toBeFalse()->and($connection->enforced)->toBeFalse()
        ->and(AuditEntry::query()->where('event', 'sso.disabled')->count())->toBe(1);
});

it('does not change what the other company has', function (): void {
    [$tenant, $owner] = ssoSetUp('acme', verified: true);
    [$other] = ssoSetUp('beta', verified: true, name: 'beta.mx');

    $this->actingAs($owner)->put(route('tenant.sso.state', $tenant), ['enabled' => true, 'enforced' => false]);

    expect(SsoConnection::query()->where('tenant_id', $other->id)->sole()->enabled)->toBeFalse()
        ->and($tenant instanceof Tenant)->toBeTrue();
});

it('turns it off when the last proven domain is taken away', function (): void {
    [$tenant, $owner] = ssoSetUp(verified: true);
    SsoConnection::query()->where('tenant_id', $tenant->id)->update(['enabled' => true]);

    $this->actingAs($owner)->put(route('tenant.sso.update', $tenant), ssoForm(['domains' => ['otro.mx']]))->assertSessionHasNoErrors();

    expect(SsoConnection::query()->where('tenant_id', $tenant->id)->sole()->enabled)->toBeFalse();
});
