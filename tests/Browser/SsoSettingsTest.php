<?php

declare(strict_types=1);

use App\Modules\Sso\Models\SsoConnection;
use App\Modules\Sso\Models\SsoDomain;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the owner the setup of single sign-on', function (bool $dark, bool $mobile): void {
    [$tenant, $owner] = ssoCompany();
    SsoConnection::factory()->create(['tenant_id' => $tenant->id]);
    SsoDomain::factory()->verified()->create(['tenant_id' => $tenant->id, 'domain' => 'acme.mx']);
    SsoDomain::factory()->create(['tenant_id' => $tenant->id, 'domain' => 'acme.com.mx']);
    test()->actingAs($owner);

    visitPage('sso/settings', '/admin/acme/sso', $dark, $mobile)
        ->assertSee('Inicio de sesión único')
        ->assertSee('Proveedor de identidad')
        ->assertSee('Falta verificar')
        ->assertSee('Activar el SSO');
})->with($looks);

it('offers the other ways in on the login page, reusing the email already typed', function (): void {
    visitPage('auth/login', '/admin/login')
        ->assertSee('o entra con')
        ->assertSee('Continuar con SSO')
        ->assertDontSee('¿Tu empresa usa inicio de sesión único?');
});
