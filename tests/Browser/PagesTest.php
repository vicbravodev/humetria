<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Pest\Browser\Api\AwaitableWebpage;

/*
 * Every Inertia page, opened the way a user reaches it, in each look: see visitPage() in tests/Pest.php.
 * Login, register and dashboard live in DesignSystemTest, which also checks the logo.
 */

dataset('looks', [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('renders the welcome page', function (bool $dark, bool $mobile): void {
    visitPage('welcome', '/', $dark, $mobile);
})->with('looks');

it('renders the forgot password page', function (bool $dark, bool $mobile): void {
    visitPage('auth/forgot-password', '/forgot-password', $dark, $mobile);
})->with('looks');

it('renders the reset password page from a real reset token', function (bool $dark, bool $mobile): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    visitPage('auth/reset-password', "/reset-password/{$token}?email=".urlencode($user->email), $dark, $mobile)
        ->assertValue('input[name="email"]', $user->email);
})->with('looks');

it('renders the verify email page for an unverified user', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->unverified()->create());

    visitPage('auth/verify-email', '/email/verify', $dark, $mobile);
})->with('looks');

it('renders the confirm password page before the security settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('auth/confirm-password', '/settings/security', $dark, $mobile);
})->with('looks');

it('renders the two-factor challenge after the password', function (bool $dark, bool $mobile): void {
    $user = User::factory()->withTwoFactor()->create();

    visitPage('auth/two-factor-challenge', '/login', $dark, $mobile, reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('@login-button')
        ->assertPathIs('/two-factor-challenge'));
})->with('looks');

it('renders the profile settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/profile', '/settings/profile', $dark, $mobile);
})->with('looks');

it('renders the appearance settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/appearance', '/settings/appearance', $dark, $mobile);
})->with('looks');

it('renders the security settings once the password is confirmed', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/security', '/settings/security', $dark, $mobile, reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('password', 'password')
        ->press('@confirm-password-button')
        ->assertPathIs('/settings/security')
        ->assertSee('Update password'));
})->with('looks');
