<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('hands the chosen companies to someone of the staff from the bar under the list', function (): void {
    signInAsSuperadmin();
    $manager = support();
    $manager->update(['name' => 'Laura Soporte']);
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertNoJavaScriptErrors()
        ->click('[aria-label="Elegir Grupo Ejemplo"]')
        ->assertSee('1 seleccionada')
        ->click('Asignar responsable')
        ->click('Laura Soporte')
        ->click('Asignar')
        ->assertSee('Laura Soporte responde ahora por 1 empresa.');

    expect($tenant->fresh()?->owner_user_id)->toBe($manager->id);
});

it('passes the accessibility and CSP checks with companies chosen and the quick view open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'seats', 'amount' => 150, 'used' => 96]);

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page
        ->click('[aria-label="Elegir Grupo Ejemplo"]')
        ->assertSee('Extender prueba'));

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page
        ->click('[aria-label="Vista rápida de Grupo Ejemplo"]')
        ->assertSee('96 de 150')
        ->assertSee('Abrir la empresa'));
})->with($looks);
