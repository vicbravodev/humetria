<?php

declare(strict_types=1);

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

$addPasskey = function (User $user): void {
    DB::table('passkeys')->insert([
        'user_id' => $user->id,
        'name' => 'Llave',
        'credential_id' => 'cred-'.$user->id,
        'credential' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
};

it('needs the current password to change the email', function (?string $password): void {
    $user = User::factory()->create(['email' => 'old@acme.mx']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'X', 'email' => 'new@acme.mx', 'current_password' => $password])
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()?->email)->toBe('old@acme.mx');
})->with(['missing' => [null], 'wrong' => ['not-the-password']]);

it('changes the email with the current password and leaves it unverified', function (): void {
    $user = User::factory()->create(['email' => 'old@acme.mx']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'X', 'email' => 'new@acme.mx', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()?->email)->toBe('new@acme.mx')->and($user->fresh()?->email_verified_at)->toBeNull();
});

it('does not ask for the password when the email stays', function (): void {
    $user = User::factory()->create(['name' => 'Old']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh()?->name)->toBe('New');
});

it('does not bind an invitation to an account whose email was never proven', function () use ($addPasskey): void {
    $attacker = User::factory()->create();
    $addPasskey($attacker);
    $this->actingAs($attacker)
        ->patch(route('profile.update'), ['name' => 'X', 'email' => 'ana@acme.mx', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();

    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->withToken('tok')->create(['email' => 'ana@acme.mx', 'name' => 'Ana']);

    // The squatter is not the invited account.
    $this->get(route('invitations.show', 'tok'))->assertInertia(fn (Assert $page) => $page->where('state', 'other_account'));
    $this->post(route('invitations.accept', 'tok'))->assertForbidden();

    // The real holder, a guest, is offered a new account, not the squatted one.
    auth()->logout();
    $this->get(route('invitations.show', 'tok'))->assertInertia(fn (Assert $page) => $page->where('state', 'new_account'));

    $this->post(route('invitations.accept', 'tok'), ['name' => 'Ana', 'password' => 'secreto-largo-123', 'password_confirmation' => 'secreto-largo-123'])
        ->assertSessionHasNoErrors();

    $ana = User::query()->where('email', 'ana@acme.mx')->sole();

    expect($ana->id)->not->toBe($attacker->id)
        ->and(TenantMembership::query()->sole()->user_id)->toBe($ana->id)
        ->and($attacker->fresh()?->email_verified_at)->toBeNull();
});

it('ends the passkeys and the other sessions of an account whose password was reset', function () use ($addPasskey): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $addPasskey($user);
    $addPasskey($other);
    foreach ([$user, $other] as $owner) {
        DB::table('sessions')->insert(['id' => 's'.$owner->id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    }

    resolve(ResetUserPassword::class)->reset($user, ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123']);

    expect($user->passkeys()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($other->passkeys()->count())->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
});
