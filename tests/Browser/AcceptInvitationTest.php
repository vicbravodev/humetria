<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the form to create the owner account', function (bool $dark, bool $mobile): void {
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']))
        ->withToken(str_repeat('a', 64))->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Acepta tu invitación')
        ->assertSee('Te invitaron como owner de Grupo Ejemplo')
        ->assertValue('#email', 'ana@acme.mx')
        ->assertValue('name', 'Ana Pérez');
})->with($looks);

it('says the link is no longer valid', function (bool $dark, bool $mobile): void {
    TenantInvitation::factory()->withToken(str_repeat('a', 64))->expired()->create();

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Este enlace ya no es válido. Pide uno nuevo al administrador de Humetría.')
        ->assertDontSee('Aceptar invitación');
})->with($looks);

it('accepts an invitation and lands on the dashboard', function (): void {
    $token = str_repeat('b', 64);
    TenantInvitation::factory()->withToken($token)->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    visit('/admin/invitations/'.$token)
        ->type('password', 'secreto-largo-123')
        ->type('password_confirmation', 'secreto-largo-123')
        ->press('Aceptar invitación')
        ->assertPathIs('/admin')
        ->assertSee('Bienvenido a Humetría.')
        ->assertNoJavaScriptErrors();

    expect(User::query()->sole()->email)->toBe('ana@acme.mx');
});
