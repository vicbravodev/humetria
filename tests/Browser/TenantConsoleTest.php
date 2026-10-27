<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the superadmin every company with its status', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create([
        'trade_name' => 'Grupo Ejemplo',
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'rfc' => 'GEJ0101011A1',
    ]);

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile)
        ->assertSee('Empresas')
        ->assertSee('1 empresa')
        ->assertSee('Grupo Ejemplo')
        ->assertSee('Grupo Ejemplo S.A. de C.V.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar');
})->with($looks);

it('says so when there are no companies yet', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('tenancy/index', '/admin/companies', $dark)
        ->assertSee('Aún no hay empresas');
})->with(['light' => false, 'dark' => true]);

it('links the console from the sidebar only for the superadmin', function (): void {
    signInAsSuperadmin();

    visit('/admin')
        ->click('Empresas')
        ->assertPathIs('/admin/companies');

    $this->actingAs(User::factory()->create());

    visit('/admin')->assertDontSee('Empresas');
});

it('creates a company from the dialog, suggesting its address from the trade name', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->type('rfc', 'gej0101011a1')
        ->type('legal_name', 'Grupo Ejemplo S.A. de C.V.')
        ->press('Dar de alta')
        ->assertSee('Empresa dada de alta.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->slug)->toBe('grupo-ejemplo');
});

it('stops following the trade name once the address has been edited', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('slug', 'mi-direccion')
        ->type('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'mi-direccion');
});

it('shows what is wrong in the dialog and keeps it open', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['rfc' => 'GEJ0101011A1']);

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('trade_name', 'Grupo Ejemplo')
        ->type('legal_name', 'Grupo Ejemplo S.A. de C.V.')
        ->type('rfc', 'GEJ0101011A1')
        ->press('Dar de alta')
        ->assertSee('Ya hay una empresa con este RFC.');

    expect(Tenant::query()->count())->toBe(1);
});

it('passes the accessibility and CSP checks with the dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Dar de alta empresa')->assertSee('Razón social'));
})->with($looks);
