<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the owner the channels of the company', function (bool $dark, bool $mobile): void {
    config(['whatsapp.enabled' => true]);
    [, $account] = paidCompany();
    $tenant = planFor($account, tenant: Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']));
    test()->actingAs(ownerOf($tenant));

    visitPage('tenancy/channels', '/admin/acme/channels', $dark, $mobile)
        ->assertSee('Canales de comunicación')
        ->assertSee('Correo')
        ->assertSee('WhatsApp')
        ->assertSee('costo adicional')
        ->assertSee('Guardar');
})->with($looks);
