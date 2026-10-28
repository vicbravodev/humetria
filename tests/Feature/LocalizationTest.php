<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Support\SessionKey;

it('shows validation messages in Mexican Spanish', function (): void {
    $this->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors([
            'email' => 'El campo correo electrónico es obligatorio.',
            'password' => 'El campo contraseña es obligatorio.',
        ]);
});

it('shows authentication failures in Spanish', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
});

it('stores time in UTC and displays it in Mexico City time', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('database.connections.pgsql.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('America/Mexico_City');
});

it('confirms profile and password updates in Spanish', function (string $method, string $route, array $data, string $toast): void {
    $this->actingAs(User::factory()->create())
        ->{$method}(route($route), $data)
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => $toast]]);
})->with([
    'profile' => ['patch', 'profile.update', ['name' => 'Ana López', 'email' => 'ana@empresa.mx'], 'Perfil actualizado.'],
    'password' => ['put', 'user-password.update', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], 'Contraseña actualizada.'],
]);

it('rejects a wrong password confirmation in Spanish', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('password.confirm.store'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors(['password' => 'La contraseña no es correcta. Revísala e inténtalo de nuevo.']);
});

it('rejects a wrong two-factor code in Spanish', function (string $field, string $message): void {
    // The factory's placeholder secret is too short to check a code against; give this user a real one.
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP')]);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.login.store'), [$field => '000000'])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'authentication code' => ['code', 'El código no es válido. Escribe el código que muestra ahora tu app de autenticación.'],
    'recovery code' => ['recovery_code', 'El código de recuperación no es válido. Revisa que lo hayas copiado completo.'],
]);

it('rejects a malformed passkey in Spanish', function (): void {
    $this->postJson(route('passkey.login'), ['credential' => ['id' => 'x', 'rawId' => 'x', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'x']]])
        ->assertJsonValidationErrors(['credential' => 'No pudimos leer la llave de acceso. Inténtalo de nuevo.']);
});

it('translates every message the passkeys package shows', function (string $english, string $spanish): void {
    expect(__($english))->toBe($spanish);
})->with([
    ['Passkey verification session expired. Please try again.', 'La verificación con llave de acceso expiró. Inténtalo de nuevo.'],
    ['Passkey registration session expired. Please try again.', 'El registro de la llave de acceso expiró. Inténtalo de nuevo.'],
    ['Unable to sign in with this account.', 'No pudimos iniciar sesión con esta llave de acceso. Entra con tu correo y contraseña.'],
    ['Unable to verify passkey. Please try again.', 'No pudimos verificar la llave de acceso. Inténtalo de nuevo.'],
    ['Passkey not recognized. It may have been removed from your account.', 'No reconocemos esta llave de acceso; es posible que se haya quitado de tu cuenta. Entra con tu correo y contraseña.'],
    ['Unable to register passkey. Please try again.', 'No pudimos registrar la llave de acceso. Inténtalo de nuevo.'],
    ['Unable to register this passkey.', 'No pudimos registrar esta llave de acceso. Prueba con otro dispositivo.'],
]);

it('generates test data in Spanish, so no page shows a Faker name like "Will"', function (): void {
    // Faker has no es_MX: that locale silently falls back to English names.
    $providers = array_map(get_class(...), fake()->getProviders());

    expect($providers)->toContain(Faker\Provider\es_ES\Person::class);
});

it('shows the page of an unknown address in Spanish', function (): void {
    config(['app.debug' => false]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('<html lang="es">', escape: false)
        ->assertSee('<title>No encontrado</title>', escape: false)
        ->assertDontSee('Not Found');
});

it('translates every title the framework error pages show', function (string $english, string $spanish): void {
    expect(__($english))->toBe($spanish);
})->with([
    ['Unauthorized', 'No autorizado'],
    ['Payment Required', 'Pago requerido'],
    ['Forbidden', 'Acceso prohibido'],
    ['Not Found', 'No encontrado'],
    ['Page Expired', 'La página expiró'],
    ['Too Many Requests', 'Demasiadas solicitudes'],
    ['Server Error', 'Error del servidor'],
    ['Service Unavailable', 'Servicio no disponible'],
]);
