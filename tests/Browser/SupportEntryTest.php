<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('enters a company as support with a reason and shows the time left in the strip', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo', 'slug' => 'grupo-ejemplo']);
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['name' => 'Ana Pérez']))->create();

    visit("/admin/companies/{$tenant->id}")
        ->assertNoJavaScriptErrors()
        ->click('Entrar como soporte')
        ->click('1 h')
        ->type('#support-reason', 'Revisar una invitación rebotada')
        ->click('[role="dialog"] button[type="submit"]')
        ->assertScript(pathBecomes('/admin/grupo-ejemplo'), true)
        ->assertSee('Estás viendo Humetría como Ana Pérez · Quedan 60 min · Salir')
        ->assertNoJavaScriptErrors();
});

it('passes the accessibility and CSP checks with the support dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    TenantMembership::factory()->for($tenant)->create();

    visitPage('tenancy/company', "/admin/companies/{$tenant->id}", $dark, $mobile, fn ($page) => $page
        ->click('Entrar como soporte')
        ->assertSee('No puedes ver respuestas individuales'));
})->with($looks);
