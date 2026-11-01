<?php

declare(strict_types=1);

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    Illuminate\Support\Facades\Log::spy();
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
    Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'account.deleted' && $context === ['user_id' => $user->id])->once();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('an account that decided a workplace review or created a campaign cannot be deleted: the user stays signed in and sees why', function (Closure $retained) {
    $user = User::factory()->create();
    Illuminate\Support\Facades\Log::spy();
    $retained($user);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasErrors(['password' => 'No puedes borrar tu cuenta: hay registros que la ley obliga a conservar a tu nombre. Pide a la empresa que te quite el acceso.'])
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->not->toBeNull();
    Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'account.deletion_blocked'
        && $context === ['user_id' => $user->id])->once();
})->with([
    'a review of the Comisión' => [function (User $user): void {
        Database\Factories\Telework\WorkplaceReviewFactory::new()->create(['decided_by' => $user->id]);
    }],
    'a campaign' => [function (User $user): void {
        Database\Factories\Campaigns\CampaignFactory::new()->create(['created_by' => $user->id]);
    }],
]);
