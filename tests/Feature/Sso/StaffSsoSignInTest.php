<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;

beforeEach(function (): void {
    config(['sso.staff' => [
        'issuer' => 'https://accounts.google.com',
        'client_id' => 'humetria-staff',
        'client_secret' => 'secret',
        'domain' => 'humetria.mx',
    ]]);
});

function staffCallback(array $query = [], array $session = []): Illuminate\Testing\TestResponse
{
    return test()->withSession(['sso.staff_nonce' => 'n-1', 'state' => 'st-1', 'code_verifier' => 'v-1', ...$session])
        ->get(route('sso.staff.callback', ['code' => 'c-1', 'state' => 'st-1', ...$query]));
}

function staffIdp(array $claims = []): void
{
    idpAnswers([
        'aud' => 'humetria-staff', 'email' => 'yo@humetria.mx', ...$claims,
    ], issuer: 'https://accounts.google.com');
}

it('sends the staff to the platform provider with state, nonce and PKCE', function (): void {
    staffIdp();

    $response = $this->get(route('sso.staff.start'));

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://accounts.google.com/authorize')
        ->and($query['client_id'])->toBe('humetria-staff')
        ->and($query['redirect_uri'])->toBe(route('sso.staff.callback'))
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and(session('sso.staff_nonce'))->toBe($query['nonce']);
});

it('answers 404 when the platform has no staff provider configured', function (): void {
    config(['sso.staff' => ['issuer' => '', 'client_id' => '', 'client_secret' => '', 'domain' => '']]);

    $this->get(route('sso.staff.start'))->assertNotFound();
});

it('signs in the staff member whose provider vouches for their email', function (): void {
    $staff = superadmin();
    $staff->forceFill(['email' => 'yo@humetria.mx'])->save();
    staffIdp();

    staffCallback()->assertRedirect();

    $this->assertAuthenticatedAs($staff);
    expect(AuditEntry::query()->where('event', 'sso.staff_signed_in')->where('actor_id', $staff->id)->count())->toBe(1);
});

it('never signs in someone who is not staff, a domain that is not ours, or an unverified email', function (string $case): void {
    $user = match ($case) {
        'not staff' => User::factory()->create(['email' => 'yo@humetria.mx']),
        default => tap(superadmin(), fn (User $u) => $u->forceFill(['email' => 'yo@humetria.mx'])->save()),
    };
    staffIdp(match ($case) {
        'other domain' => ['email' => 'yo@otra.mx'],
        'unverified' => ['email_verified' => false],
        default => [],
    });

    staffCallback()->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->exists)->toBeTrue();
})->with(['not staff', 'other domain', 'unverified']);

it('refuses a callback without the session of a start', function (): void {
    staffIdp();

    $this->get(route('sso.staff.callback', ['code' => 'c-1', 'state' => 'st-1']))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('sends a staff email typed in the login to the platform provider, and answers alike when the staff provider is off', function (): void {
    $this->post(route('sso.discover'), ['email' => 'yo@HUMETRIA.mx'])->assertRedirect(route('sso.staff.start'));

    config(['sso.staff' => ['issuer' => '', 'client_id' => '', 'client_secret' => '', 'domain' => '']]);

    $this->from(route('login'))->post(route('sso.discover'), ['email' => 'yo@humetria.mx'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('sso');
});
