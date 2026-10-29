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

it('tells a person without companies to contact the administrator, and lets them sign out', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('tenancy/companies', '/admin', $dark, $mobile)
        ->assertSee('Aún no tienes acceso a ninguna empresa. Contacta al administrador de Humetría.')
        ->assertSee('Cerrar sesión');
})->with($looks);

it('lists the companies of a person with several, the blocked one with its status', function (bool $dark, bool $mobile): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->deactivated()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visitPage('tenancy/companies', '/admin', $dark, $mobile)
        ->assertSee('Elige una empresa')
        ->assertSee('Acme Industrial')
        ->assertSee('Sin iniciar')
        ->assertSee('Beta Foods')
        ->assertSee('Desactivada');
})->with($looks);

it('opens the company that was chosen from the list', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visit('/admin')
        ->click('Beta Foods')
        ->assertPathIs('/admin/beta');
});
