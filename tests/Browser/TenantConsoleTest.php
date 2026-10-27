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

it('edits the legal name from the dialog and shows it in the row', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo', 'legal_name' => 'Grupo Ejemplo S.A. de C.V.', 'slug' => 'grupo-ejemplo']);

    visit('/admin/companies')
        ->click('Editar')
        ->assertSee('Editar empresa')
        ->assertValue('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->type('legal_name', 'Grupo Ejemplo Corregido S.A.')
        ->type('trade_name', 'Grupo Nuevo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->assertDontSee('Las direcciones anteriores')
        ->press('Guardar')
        ->assertSee('Empresa actualizada.')
        ->assertSee('Grupo Ejemplo Corregido S.A.')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->slug)->toBe('grupo-ejemplo');
});

it('warns that the old addresses stop working when the address is edited', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['slug' => 'grupo-ejemplo']);

    visit('/admin/companies')
        ->click('Editar')
        ->type('slug', 'otra-direccion')
        ->assertSee('Las direcciones anteriores de esta empresa dejarán de funcionar.');
});

it('passes the accessibility and CSP checks with the edit dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Editar')->assertSee('Editar empresa'));
})->with($looks);

it('activates a company that has not started, choosing the date', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    $until = now('America/Mexico_City')->addMonths(2);

    visit('/admin/companies')
        ->assertSee('Sin iniciar')
        ->click('Activar')
        ->assertSee('Activar empresa')
        ->type('active_until', $until->format('Y-m-d'))
        // The row's own "Activar" button shares the text, so the dialog's submit is picked by its place.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa activa hasta')
        ->assertSee('Activa hasta '.str_replace('.', '', $until->isoFormat('D MMM YYYY')))
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->status()->value)->toBe('active');
});

it('offers to reactivate a blocked company', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->deactivated()->create();

    visit('/admin/companies')
        ->click('Reactivar')
        ->assertSee('Reactivar empresa');
});

it('passes the accessibility and CSP checks with the activation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Activar')->assertSee('Activar empresa'));
})->with($looks);

it('deactivates an active company after confirming, and hides the button afterwards', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Activa hasta')
        ->click('Desactivar')
        ->assertSee('¿Desactivar Grupo Ejemplo?')
        ->assertSee('Sus datos se conservan.')
        ->assertDontSee('La prueba no se puede reanudar.')
        // The row's own "Desactivar" button shares the text, so the dialog's submit is picked by its place.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa desactivada.')
        ->assertSee('Desactivada el')
        ->assertDontSee('Desactivar')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->status()->value)->toBe('deactivated');
});

it('warns that a trial cannot be resumed once the company is deactivated', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->inTrial(now())->create();

    visit('/admin/companies')
        ->click('Desactivar')
        ->assertSee('La prueba no se puede reanudar.');
});

it('does not offer to deactivate a blocked company', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->deactivated()->create();

    visit('/admin/companies')->assertDontSee('Desactivar');
});

it('passes the accessibility and CSP checks with the deactivation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Desactivar')->assertSee('Sus datos se conservan.'));
})->with($looks);
