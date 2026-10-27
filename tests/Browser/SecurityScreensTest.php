<?php

declare(strict_types=1);

use App\Models\User;
use Pest\Browser\Api\AwaitableWebpage;

/*
 * Characterization tests for the security screens: they pin the behavior of the
 * starter-kit components so splitting them cannot change what the user sees.
 */

function visitSecuritySettings(User $user): AwaitableWebpage
{
    test()->actingAs($user);

    return visitPage('settings/security', '/settings/security', reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->assertSee('Confirma tu contraseña para continuar')
        ->fill('password', 'password')
        ->press('@confirm-password-button')
        ->assertPathIs('/settings/security'));
}

it('renders the password, two-factor and passkeys sections', function (): void {
    visitSecuritySettings(User::factory()->create())
        ->assertSee('Cambiar contraseña')
        ->assertSee('Contraseña actual')
        ->assertSee('Nueva contraseña')
        ->assertSee('Two-factor authentication')
        ->assertSee('Enable 2FA')
        ->assertSee('Passkeys')
        ->assertSee('No passkeys yet')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('opens the setup modal with the QR code and the manual key', function (): void {
    visitSecuritySettings(User::factory()->create())
        ->press('Enable 2FA')
        ->assertSee('Enable two-factor authentication')
        ->assertSee('or, enter the code manually')
        ->assertPresent('[role="dialog"] svg')
        ->assertPresent('[role="dialog"] input[readonly]')
        ->press('Continue')
        ->assertSee('Verify authentication code')
        ->assertPresent('[role="dialog"] input[name="code"]')
        ->assertButtonDisabled('Confirm')
        ->press('Back')
        ->assertSee('Enable two-factor authentication')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('reveals the recovery codes of a user with two-factor enabled', function (): void {
    visitSecuritySettings(User::factory()->withTwoFactor()->create())
        ->assertSee('Disable 2FA')
        ->assertSee('2FA recovery codes')
        ->assertAttribute('#recovery-codes-section', 'aria-hidden', 'true')
        ->assertNotPresent('button[aria-describedby="regenerate-warning"]')
        ->press('View recovery codes')
        ->assertAttribute('#recovery-codes-section', 'aria-hidden', 'false')
        ->assertSee('recovery-code-1')
        ->assertPresent('button[aria-describedby="regenerate-warning"]')
        ->assertSee('Hide recovery codes')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('switches the two-factor challenge between code and recovery code', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    visitPage('auth/two-factor-challenge', '/login', reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('@login-button')
        ->assertPathIs('/two-factor-challenge'))
        ->assertSee('Authentication code')
        ->assertPresent('input[name="code"]')
        ->assertMissing('input[name="recovery_code"]')
        ->click('login using a recovery code')
        ->assertSee('Recovery code')
        ->assertPresent('input[name="recovery_code"]')
        ->assertNotPresent('input[name="code"]')
        ->click('login using an authentication code')
        ->assertSee('Authentication code')
        ->assertPresent('input[name="code"]')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});
