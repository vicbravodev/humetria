<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Validation\Rules\Password;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the form to create the owner account', function (bool $dark, bool $mobile): void {
    $olga = User::factory()->create(['name' => 'Olga Owner']);
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']))->role(TenantRole::Hr)
        ->withToken(str_repeat('a', 64))->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx', 'invited_by' => $olga->id]);

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Olga Owner te invitó a')
        ->assertSee('Grupo Ejemplo')
        ->assertSee('como RH · opera evaluaciones, centros y empleados')
        ->assertSee('Crea tu cuenta')
        ->assertValue('#email', 'ana@acme.mx')
        ->assertValue('name', 'Ana Pérez')
        ->assertAttribute('#password', 'passwordrules', Password::defaults()->toPasswordRulesString());
})->with($looks);

it('says the link is no longer valid', function (bool $dark, bool $mobile): void {
    TenantInvitation::factory()->withToken(str_repeat('a', 64))->expired()->create();

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Este enlace ya no es válido')
        ->assertSee('Pide uno nuevo a quien te invitó.')
        ->assertDontSee('Crear cuenta y entrar');
})->with($looks);

it('asks an existing account to sign in', function (bool $dark, bool $mobile): void {
    User::factory()->create(['email' => 'ana@acme.mx']);
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']))
        ->withToken(str_repeat('a', 64))->create(['email' => 'ana@acme.mx']);

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Ya tienes una cuenta en Humetría')
        ->assertSee('Inicia sesión para unirte a Grupo Ejemplo.')
        ->assertSee('Iniciar sesión')
        ->assertDontSee('Contraseña');
})->with($looks);

it('asks the signed-in invited account to confirm', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create(['email' => 'ana@acme.mx']));
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']))
        ->withToken(str_repeat('a', 64))->create(['email' => 'ana@acme.mx']);

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Únete a Grupo Ejemplo')
        ->assertSee('Aceptar invitación')
        ->assertDontSee('Contraseña');
})->with($looks);

it('asks another signed-in account to sign out', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create(['email' => 'beto@otra.mx']));
    TenantInvitation::factory()->withToken(str_repeat('a', 64))->create(['email' => 'ana@acme.mx']);

    visitPage('tenancy/accept-invitation', '/admin/invitations/'.str_repeat('a', 64), $dark, $mobile)
        ->assertSee('Esta invitación es para ana@acme.mx. Cierra sesión para continuar.')
        ->assertSee('Cerrar sesión y continuar')
        ->assertDontSee('Aceptar invitación');
})->with($looks);

it('signs out another account, signs in the invited one and accepts', function (): void {
    $token = str_repeat('c', 64);
    $this->actingAs(User::factory()->create(['email' => 'beto@otra.mx']));
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    $invitation = TenantInvitation::factory()->withToken($token)->create(['email' => 'ana@acme.mx']);

    visit('/admin/invitations/'.$token)
        ->press('Cerrar sesión y continuar')
        ->assertPathIs('/admin/invitations/'.$token)
        ->click('Iniciar sesión')
        ->assertPathIs('/admin/login')
        ->type('email', 'ana@acme.mx')
        ->type('password', 'password')
        ->press('Iniciar sesión')
        ->assertPathIs('/admin/invitations/'.$token)
        ->press('Aceptar invitación')
        ->assertPathIs('/admin/'.$invitation->tenant->slug)
        ->assertSee('Te damos la bienvenida a Humetría.')
        ->assertNoJavaScriptErrors();

    expect(TenantMembership::query()->sole()->user_id)->toBe($ana->id);
});

it('accepts an invitation and lands on the dashboard', function (): void {
    $token = str_repeat('b', 64);
    $invitation = TenantInvitation::factory()->withToken($token)->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    visit('/admin/invitations/'.$token)
        ->type('password', 'secreto-largo-123')
        ->type('password_confirmation', 'secreto-largo-123')
        ->press('Crear cuenta y entrar')
        ->assertPathIs('/admin/'.$invitation->tenant->slug)
        ->assertSee('Te damos la bienvenida a Humetría.')
        ->assertNoJavaScriptErrors();

    expect(User::query()->sole()->email)->toBe('ana@acme.mx');
});
