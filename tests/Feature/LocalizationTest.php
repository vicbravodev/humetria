<?php

declare(strict_types=1);

use App\Models\User;

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
