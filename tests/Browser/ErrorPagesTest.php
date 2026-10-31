<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

dataset('error looks', [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('shows its own page for an address that does not exist', function (bool $dark, bool $mobile): void {
    visitPage('error', '/no-such-page', $dark, $mobile)
        ->assertSee('Error 404')
        ->assertSee('No encontramos esta página')
        ->assertSee('Ir al inicio');
})->with('error looks');

it('tells a person which role keeps them out of a section, and takes them back to the panel', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo']);
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visitPage('error', route('tenant.employees.index', $tenant, absolute: false), $dark, $mobile)
        ->assertSee('Error 403')
        ->assertSee('Esta sección no está en tu rol')
        ->assertSee('Tu rol en Autopartes Demo es Consulta, que ve resultados e informes.')
        ->click('Volver al panel')
        ->assertPathBeginsWith('/admin');
})->with('error looks');
