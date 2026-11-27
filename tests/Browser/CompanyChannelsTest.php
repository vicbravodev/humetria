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

it('says on the demo that a guided session sends WhatsApp for real, by mail and WhatsApp at once', function (): void {
    config(['whatsapp.enabled' => true]);
    $tenant = Tenant::factory()->demo()->create(['slug' => 'demo-acme']);
    test()->actingAs(ownerOf($tenant));

    visit('/admin/demo-acme/channels')
        ->assertSee('En una sesión guiada, la demo manda WhatsApp de verdad.')
        ->assertSee('salen por correo y WhatsApp a la vez')
        ->assertDontSee('nunca manda WhatsApp')
        ->assertNoJavaScriptErrors();
});

it('tells the staff working as the owner that only the owner saves the channels, with no button that answers 404', function (): void {
    config(['whatsapp.enabled' => true]);
    [, $account] = paidCompany();
    $tenant = planFor($account, tenant: Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']));
    ownerOf($tenant);
    test()->actingAs(superadmin())->post(route('companies.impersonate', $tenant));

    visit('/admin/acme/channels')
        ->assertSee('Solo el dueño de la empresa puede cambiar los canales')
        ->assertDontSee('Guardar')
        ->assertNoJavaScriptErrors();
});
