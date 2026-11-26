<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantNote;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the company with its tabs, its people and the side with contact, notes and risk zone', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);
    memberOf($tenant, TenantRole::Owner);
    TenantNote::factory()->create(['tenant_id' => $tenant->id, 'body' => 'Paga en quincena']);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}?tab=miembros", $dark, $mobile)
        ->assertSee('Uso y límites')
        ->assertSee('Cuenta y contacto')
        ->assertSee('Miembro owner')
        ->assertSee('Paga en quincena')
        ->assertSee('Zona de riesgo');
})->with($looks);

it('writes an internal note from the side of the page', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();

    visit("/admin/companies/{$tenant->id}")
        ->assertNoJavaScriptErrors()
        ->click('Agregar')
        ->type('#note-body', 'Pidió mover la campaña')
        ->click('Guardar nota')
        ->assertSee('Nota guardada.')
        ->assertSee('Pidió mover la campaña');
});

it('suspends only once the company name is typed', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);

    visit("/admin/companies/{$tenant->id}")
        ->assertNoJavaScriptErrors()
        ->click('Suspender empresa…')
        ->type('#tenant-reason', 'Saldo vencido')
        ->assertScript('() => document.querySelector(\'[role="dialog"] button[type="submit"]\').disabled', true)
        ->type('#risk-confirmation', 'Textiles del Norte')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertSee('Suspendida');

    expect($tenant->fresh()?->restriction)->toBe(TenantRestriction::Suspended);
});

it('passes the accessibility and CSP checks with the risk dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}", $dark, $mobile, fn ($page) => $page
        ->click('Archivar empresa…')
        ->assertSee('Escribe «Textiles del Norte» para confirmar'));
})->with($looks);
