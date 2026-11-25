<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;

it('shows the catalog in force read only, which module each plan brings and the negotiated prices', function (bool $dark, bool $mobile): void {
    $this->travelTo('2026-11-26 15:00:00');
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Aceros Monterrey']);
    Subscription::factory()->plan(Plan::Enterprise)->create(['tenant_id' => $tenant->id, 'negotiated_price_cents' => 1_500_000, 'negotiated_valid_until' => '2027-01-20']);

    visitPage('billing/plans', '/admin/billing/plans', $dark, $mobile)
        ->assertSee('Planes y precios')
        ->assertSee('Solo lectura')
        ->assertSee('Catálogo v1')
        ->assertSee('Cumplimiento')
        ->assertSee('Qué módulo trae cada plan')
        ->assertSee('Organigrama')
        ->assertSee('Aceros Monterrey')
        ->assertSee('$15,000')
        ->assertSee('20 ene 2027')
        ->assertDontSee('Nuevo plan')
        ->assertDontSee('Editar plan');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('says nobody has a negotiated price yet', function (): void {
    signInAsSuperadmin();

    visitPage('billing/plans', '/admin/billing/plans')
        ->assertSee('Nadie tiene un precio pactado');
});
