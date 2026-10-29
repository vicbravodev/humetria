<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Pest\Browser\Api\AwaitableWebpage;

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
        // With nothing to choose, the page does not promise a choice.
        ->assertDontSee('Elige una empresa')
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

it('switches from one company to another from the sidebar', function (bool $dark): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme', $dark, reach: fn (AwaitableWebpage $page) => $page
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Acme Industrial')
        ->click('[data-test="tenant-switcher"]')
        ->click('Beta Foods')
        ->assertPathIs('/admin/beta'))
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Beta Foods')
        // The sidebar's Panel now belongs to the company that was chosen.
        ->assertScript('() => document.querySelector(\'[data-sidebar="menu-button"][data-active="true"]\').getAttribute("href")', '/admin/beta');
})->with(['light' => false, 'dark' => true]);

it('shows the only company as plain text, with no menu', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Acme Industrial')
        ->assertMissing('button[data-test="tenant-switcher"]');
});

it('says how many days of trial a company has left next to its name', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->inTrial(now())->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Acme Industrial')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Prueba');
});

it('folds the switcher to its icon without overflowing the sidebar', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial Con Un Nombre Muy Largo', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->click('[data-sidebar="trigger"]')
        ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)')
        ->assertScript('() => { const box = document.querySelector(\'[data-test="tenant-switcher"]\').getBoundingClientRect(); return box.width >= 44 && box.width < 56 && document.querySelector(\'[data-test="tenant-switcher"]\').scrollWidth <= box.width; }');
});
