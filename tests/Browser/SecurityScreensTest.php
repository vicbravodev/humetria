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

    return visitPage('settings/security', '/admin/settings/security', reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->assertSee('Confirma tu contraseña para continuar')
        ->fill('password', 'password')
        ->press('@confirm-password-button')
        ->assertPathIs('/admin/settings/security'));
}

it('renders the password, two-factor and passkeys sections', function (): void {
    visitSecuritySettings(User::factory()->create())
        ->assertSee('Guardar contraseña')
        ->assertSee('Contraseña actual')
        ->assertSee('Nueva contraseña')
        ->assertSee('Al menos 8 caracteres')
        ->assertSee('Verificación en dos pasos')
        ->assertSee('Desactivada')
        ->assertPresent('button[aria-label="Activar verificación en dos pasos"]')
        ->assertSee('Llaves de acceso')
        ->assertSee('Agregar llave')
        ->assertSee('Aún no tienes llaves de acceso')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('opens the setup modal with the QR code and the manual key', function (): void {
    visitSecuritySettings(User::factory()->create())
        ->click('button[aria-label="Activar verificación en dos pasos"]')
        ->assertSee('Activar la verificación en dos pasos')
        ->assertSee('o escribe la clave manualmente')
        ->assertPresent('[role="dialog"] svg')
        ->assertPresent('[role="dialog"] input[readonly]')
        ->press('Continuar')
        ->assertSee('Confirma tu código de verificación')
        ->assertPresent('[role="dialog"] input[name="code"]')
        ->assertButtonDisabled('Confirmar')
        ->press('Atrás')
        ->assertSee('Activar la verificación en dos pasos')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('reveals the recovery codes of a user with two-factor enabled', function (): void {
    visitSecuritySettings(User::factory()->withTwoFactor()->create())
        ->assertSee('Activada')
        ->assertPresent('button[aria-label="Desactivar verificación en dos pasos"]')
        ->assertSee('Códigos de recuperación')
        ->assertAttribute('#recovery-codes-section', 'aria-hidden', 'true')
        ->assertNotPresent('button[aria-describedby="regenerate-warning"]')
        ->press('Ver códigos de recuperación')
        ->assertAttribute('#recovery-codes-section', 'aria-hidden', 'false')
        ->assertSee('Xk3pQ9aZ7m-Lw2rT8vB4n')
        ->assertPresent('button[aria-describedby="regenerate-warning"]')
        ->assertSee('Ocultar códigos de recuperación')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('switches the two-factor challenge between code and recovery code', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    visitPage('auth/two-factor-challenge', '/admin/login', reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('@login-button')
        ->assertPathIs('/admin/two-factor-challenge'))
        ->assertSee('Código de verificación')
        ->assertPresent('input[name="code"]')
        ->assertMissing('input[name="recovery_code"]')
        ->click('entrar con un código de recuperación')
        ->assertSee('Código de recuperación')
        ->assertPresent('input[name="recovery_code"]')
        ->assertNotPresent('input[name="code"]')
        ->click('entrar con un código de verificación')
        ->assertSee('Código de verificación')
        ->assertPresent('input[name="code"]')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});
