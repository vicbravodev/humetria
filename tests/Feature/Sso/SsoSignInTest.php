<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;

const SSO_GENERIC = 'No pudimos iniciar tu sesión con SSO. Si el problema sigue, pide ayuda a quien administra tu cuenta.';

it('sends the person to the identity provider of that company, with state, nonce and PKCE', function (): void {
    ssoReady();
    idpAnswers();

    $response = $this->get(route('sso.start', 'acme'));

    $response->assertRedirect();
    $url = parse_url((string) $response->headers->get('Location'));
    parse_str($url['query'] ?? '', $query);

    expect($url['host'])->toBe('idp.acme.test')->and($url['path'])->toBe('/authorize')
        ->and($query['client_id'])->toBe('humetria-acme')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('openid email')
        ->and($query['redirect_uri'])->toBe(route('sso.callback'))
        ->and($query['state'])->not->toBeEmpty()
        ->and($query['nonce'])->not->toBeEmpty()
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->not->toBeEmpty()
        ->and(session('sso.nonce'))->toBe($query['nonce']);
});

it('answers the same 404 whatever is missing for single sign-on', function (string $case): void {
    [$tenant] = ssoReady();
    idpAnswers();

    match ($case) {
        'unknown company' => null,
        'plan without the module' => TenantModuleOff($tenant),
        'switched off' => SsoConnection::query()->update(['enabled' => false]),
        'no proven domain' => SsoDomain::query()->update(['verified_at' => null]),
    };

    $this->get(route('sso.start', $case === 'unknown company' ? 'nadie' : 'acme'))->assertNotFound();
})->with(['unknown company', 'plan without the module', 'switched off', 'no proven domain']);

function TenantModuleOff(Tenant $tenant): void
{
    App\Modules\Tenancy\Models\TenantModule::query()->where('tenant_id', $tenant->id)->delete();
    resolve(App\Modules\Tenancy\Support\ModuleStates::class)->forget();
}

it('uses the provider of the company in the address, never another one', function (): void {
    ssoReady('acme', 'acme.mx', 'https://idp.acme.test');
    ssoReady('beta', 'beta.mx', 'https://login.beta.test');
    idpAnswers(issuer: 'https://login.beta.test');

    $location = (string) $this->get(route('sso.start', 'beta'))->headers->get('Location');

    expect($location)->toStartWith('https://login.beta.test/authorize');
});

it('signs in the member of the company whose provider vouches for their email', function (): void {
    Log::spy();
    [$tenant, , $member] = ssoReady();
    idpAnswers();

    ssoCallback($tenant)->assertRedirect(route('tenant.dashboard', 'acme'));

    $this->assertAuthenticatedAs($member);
    expect(session('sso.tenants'))->toBe([$tenant->id])->and(session('sso.user_id'))->toBe($member->id)
        ->and(AuditEntry::query()->where('event', 'sso.signed_in')->where('tenant_id', $tenant->id)->where('actor_id', $member->id)->count())->toBe(1);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'sso.signed_in' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'rh@acme.mx'))->once();
});

it('matches the email without regard to case, and takes the Entra proof of the domain in place of email_verified', function (): void {
    [$tenant, , $member] = ssoReady();
    idpAnswers(['email' => 'RH@Acme.MX', 'email_verified' => null, 'xms_edov' => true]);

    ssoCallback($tenant)->assertRedirect(route('tenant.dashboard', 'acme'));

    $this->assertAuthenticatedAs($member);
});

it('gives the same generic answer, and no session, to everything that cannot sign in', function (string $case, string $reason): void {
    Log::spy();
    [$tenant] = ssoReady();
    $stranger = User::factory()->create(['email' => 'otra@acme.mx']);
    $outsider = User::factory()->create(['email' => 'rh@acme.mx.example.org']);
    $query = [];
    $session = [];

    match ($case) {
        'email not verified by the provider' => idpAnswers(['email_verified' => false]),
        'no verification claim' => idpAnswers(['email_verified' => null]),
        'a nonce that is not ours' => idpAnswers(['nonce' => 'otro']),
        'a token for another application' => idpAnswers(['aud' => 'otra-app']),
        'an expired token' => idpAnswers(['exp' => now()->subMinutes(5)->timestamp]),
        'a token from another issuer' => idpAnswers(['iss' => 'https://evil.test']),
        'a domain the company did not prove' => idpAnswers(['email' => 'rh@otra.mx']),
        'someone with an account who is not a member' => idpAnswers(['email' => $stranger->email]),
        'a lookalike domain' => idpAnswers(['email' => $outsider->email]),
        'nobody with that email' => idpAnswers(['email' => 'fantasma@acme.mx']),
        'a state that is not ours' => [idpAnswers(), $query = ['state' => 'falso']],
        'a callback with no sign-in started' => [idpAnswers(), $session = ['sso.tenant_id' => null]],
        'the provider refusing the code' => idpAnswers(tokenStatus: 400),
        'a provider that names another issuer' => idpAnswers(discovery: ['issuer' => 'https://evil.test']),
        'a provider whose token endpoint is not https' => idpAnswers(discovery: ['token_endpoint' => 'http://idp.acme.test/token']),
    };

    ssoCallback($tenant, $query, $session)->assertRedirect(route('login'))->assertSessionHasErrors(['sso' => SSO_GENERIC]);

    $this->assertGuest();
    expect(User::query()->count())->toBe(4)->and(TenantMembership::query()->count())->toBe(2);
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'sso.sign_in_failed' && $context['reason'] === $reason)->once();
})->with([
    'email not verified by the provider' => ['email not verified by the provider', 'email_not_verified'],
    'no verification claim' => ['no verification claim', 'email_not_verified'],
    'a nonce that is not ours' => ['a nonce that is not ours', 'wrong_nonce'],
    'a token for another application' => ['a token for another application', 'wrong_audience'],
    'an expired token' => ['an expired token', 'expired_token'],
    'a token from another issuer' => ['a token from another issuer', 'wrong_issuer'],
    'a domain the company did not prove' => ['a domain the company did not prove', 'domain_not_proven'],
    'someone with an account who is not a member' => ['someone with an account who is not a member', 'no_member'],
    'a lookalike domain' => ['a lookalike domain', 'domain_not_proven'],
    'nobody with that email' => ['nobody with that email', 'no_member'],
    'a state that is not ours' => ['a state that is not ours', 'invalid_state'],
    'a callback with no sign-in started' => ['a callback with no sign-in started', 'no_sign_in_started'],
    'the provider refusing the code' => ['the provider refusing the code', 'token_exchange_failed'],
    'a provider that names another issuer' => ['a provider that names another issuer', 'discovery_issuer_mismatch'],
    'a provider whose token endpoint is not https' => ['a provider whose token endpoint is not https', 'discovery_endpoints_invalid'],
]);

it('never lets Humetría staff in through a company provider', function (): void {
    [$tenant, , $member] = ssoReady();
    $role = User::SUPPORT;
    Spatie\Permission\Models\Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web', 'tenant_id' => null]);
    DB::table('model_has_roles')->insert(['role_id' => Spatie\Permission\Models\Role::query()->where('name', $role)->value('id'), 'model_type' => $member->getMorphClass(), 'model_id' => $member->id, 'tenant_id' => null]);
    idpAnswers();

    ssoCallback($tenant)->assertRedirect(route('login'))->assertSessionHasErrors(['sso' => SSO_GENERIC]);

    $this->assertGuest();
});

it('does not sign in through a company whose single sign-on was switched off after the start', function (): void {
    [$tenant] = ssoReady();
    idpAnswers();
    SsoConnection::query()->update(['enabled' => false]);

    ssoCallback($tenant)->assertRedirect(route('login'))->assertSessionHasErrors(['sso' => SSO_GENERIC]);

    $this->assertGuest();
});

it('logs why a sign-in failed, without the email or any secret', function (): void {
    Log::spy();
    [$tenant] = ssoReady();
    idpAnswers(['email' => 'fantasma@acme.mx']);

    ssoCallback($tenant);

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context): bool => $event === 'sso.sign_in_failed'
        && $context['reason'] === 'no_member'
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'fantasma'))->once();
});

it('finds the company of a work email by its proven domain', function (): void {
    ssoReady();

    $this->post(route('sso.discover'), ['email' => 'cualquiera@ACME.mx'])->assertRedirect(route('sso.start', 'acme'));
});

it('answers alike when no company has that domain, or only an unproven one', function (): void {
    ssoReady();
    SsoDomain::factory()->create(['tenant_id' => Tenant::factory()->create(['slug' => 'beta'])->id, 'domain' => 'beta.mx']);

    foreach (['x@nadie.mx', 'x@beta.mx', 'sin-arroba'] as $email) {
        $this->from(route('login'))->post(route('sso.discover'), ['email' => $email])->assertRedirect(route('login'))->assertSessionHasErrors(['sso' => SSO_GENERIC]);
    }
});

it('throttles the start of a sign-in per address', function (): void {
    ssoReady();
    idpAnswers();

    foreach (range(1, 20) as $ignored) {
        $this->get(route('sso.start', 'acme'));
    }

    $this->get(route('sso.start', 'acme'))->assertStatus(429);
});
