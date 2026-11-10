<?php

declare(strict_types=1);

use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\TenantModule;
use Carbon\CarbonImmutable;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows a company with its modules and where each comes from', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Norte']);
    resolve(CompanyModules::class)->syncPlan($tenant->id, ['nom035', 'nom037', 'pulse'], CarbonImmutable::now());
    TenantModule::factory()->for($tenant)->create(['module' => 'seating', 'source' => 'grant', 'reason' => 'Piloto de tres semanas']);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}", $dark, $mobile)
        ->assertSee('Autopartes del Norte')
        ->assertSee('Módulos')
        ->assertSee('NOM-035')
        ->assertSee('Habilitado')
        ->assertSee('Plan')
        ->assertSee('Concedido a mano')
        ->assertSee('Motivo: Piloto de tres semanas')
        ->assertSee('Registro de jornada')
        ->assertSee('No habilitado')
        ->assertSee('Aún no está disponible')
        ->assertSee('Conceder')
        ->assertSee('Quitar');
})->with($looks);

it('shows a company that has no plan with everything it has today, as it was before', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}")
        ->assertSee('Incluido antes del plan')
        ->assertSee('Ley Silla')
        ->assertSee('Buzón de quejas');
});

it('shows support the modules without the buttons that change them', function (): void {
    $this->actingAs(support());
    $tenant = Tenant::factory()->create();

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}")
        ->assertSee('NOM-037')
        ->assertDontSee('Conceder')
        ->assertMissing('[aria-label^="Quitar "]');
});

it('takes a module away from the company page and gives it back', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Cliente Piloto']);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}")
        ->click('[aria-label="Quitar Ley Silla"]')
        ->assertSee('Se esconde del menú')
        ->fill('reason', 'Pidió pausar Ley Silla')
        ->press('Quitar módulo')
        ->assertSee('Módulo quitado.')
        ->assertSee('Quitado a mano')
        ->assertSee('Motivo: Pidió pausar Ley Silla')
        ->click('[aria-label="Conceder Ley Silla"]')
        ->fill('reason', 'Se reanuda')
        ->press('Conceder módulo')
        ->assertSee('Módulo concedido.');

    resolve(App\Modules\Tenancy\Support\ModuleStates::class)->forget();
    expect(resolve(CompanyModules::class)->enabled($tenant->id, 'seating'))->toBeTrue();
});

it('opens a company\'s modules from its name in the console', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Cliente Visible']);

    visitPage('tenancy/index', '/admin/companies')
        ->click('Cliente Visible')
        ->assertSee('Módulos')
        ->assertSee('NOM-035');
});

it('shows what the company has used of the people and centers its plan covers', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'seats', 'amount' => 50, 'used' => 42]);
    TenantLimit::factory()->create(['tenant_id' => $tenant->id, 'resource' => 'centers', 'amount' => 2, 'used' => 3]);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}", $dark, $mobile)
        ->assertSee('Límites')
        ->assertSee('Personas')
        ->assertSee('de 50')
        ->assertSee('Quedan 8 lugares.')
        ->assertSee('Centros de trabajo')
        ->assertSee('Por encima')
        ->assertSee('Tiene 1 lugar de más');
})->with($looks);

it('says a company without a plan has no limits', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}")->assertSee('Sin plan asignado: la empresa no tiene límites.');
});

it('shows the tokens of Humi this month, with the quota and the months before', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create();
    UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'used' => 12_000, 'reserved' => 0]);
    UsageCounter::factory()->create(['tenant_id' => $tenant->id, 'period_start' => '2026-01-01', 'used' => 7000, 'quota' => 15_000]);

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}", $dark, $mobile)
        ->assertSee('Consumo de Humi')
        ->assertSee('tokens usados')
        ->assertSee('2,000,000')
        ->assertSee('Agregar tokens')
        ->assertSee('Enero 2026')
        ->assertSee('15,000');
})->with($looks);
