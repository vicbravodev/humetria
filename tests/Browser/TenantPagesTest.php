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
    TenantMembership::factory()->for(Tenant::factory()->inTrial(now()->subDays(10))->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Acme Industrial')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Prueba · quedan 4 días');
});

it('shows no trial label for a company that is not in trial', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->activeUntil(now()->addMonth())->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->assertSeeIn('[data-test="tenant-switcher"]', 'Acme Industrial')
        ->assertDontSeeIn('[data-test="tenant-switcher"]', 'Prueba');
});

const SWITCHER = 'document.querySelector(\'[data-test="tenant-switcher"]\')';

it('folds the switcher to its icon, keeping its name for screen readers', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->inTrial(now()->subDays(10))->create(['trade_name' => 'Acme Industrial Con Un Nombre Muy Largo', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->click('[data-sidebar="trigger"]')
        ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)')
        ->assertScript('() => { const box = '.SWITCHER.'.getBoundingClientRect(); return box.width >= 44 && box.width < 56 && '.SWITCHER.'.scrollWidth <= box.width; }')
        // Folded, the text leaves the screen but not the accessibility tree: the trigger is still named by company and trial.
        ->assertScript('() => '.SWITCHER.'.innerText.includes("Acme Industrial Con Un Nombre Muy Largo") && '.SWITCHER.'.innerText.includes("Prueba · quedan 4 días")')
        ->assertNoAccessibilityIssues(1);
});

it('folds the plain-text switcher without losing the name or overflowing', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('dashboard', '/admin/acme')
        ->click('[data-sidebar="trigger"]')
        ->assertScript('() => Promise.all(document.getAnimations().map((a) => a.finished)).then(() => true)')
        ->assertScript('() => { const box = '.SWITCHER.'.getBoundingClientRect(); return box.width <= 1 && box.height <= 1; }')
        // Off screen, not display: none, so the name stays in the accessibility tree.
        ->assertScript('() => getComputedStyle('.SWITCHER.').display !== "none" && '.SWITCHER.'.textContent.includes("Acme Industrial")')
        ->assertNoAccessibilityIssues(1);
});

it('tells a person with one suspended company so, with no way to switch', function (bool $dark, bool $mobile): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->deactivated()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visitPage('tenancy/blocked', '/admin/acme', $dark, $mobile)
        ->assertSee('El acceso a Acme Industrial está en pausa')
        ->assertSee('Para reactivarlo, contacta al administrador de Humetría.')
        ->assertSee('Cerrar sesión')
        ->assertDontSee('Cambiar de empresa')
        // Nothing to close it with: it is not a notice but a lock.
        ->assertMissing('[data-test="tenant-blocked-dialog"] > button');
})->with($looks);

it('offers a person with several companies to move to another', function (bool $dark, bool $mobile): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->deactivated()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Beta Foods', 'slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visitPage('tenancy/blocked', '/admin/acme', $dark, $mobile)
        ->assertSee('El acceso a Acme Industrial está en pausa')
        ->assertSee('Cambiar de empresa')
        ->assertSee('Cerrar sesión');
})->with($looks);

it('does not let Escape close the blocked modal', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->deactivated()->create(['trade_name' => 'Acme Industrial', 'slug' => 'acme']))->for($user)->create();
    $this->actingAs($user);

    visit('/admin/acme')
        ->assertSee('El acceso a Acme Industrial está en pausa')
        ->keys('[data-test="tenant-blocked-dialog"]', 'Escape')
        // Let a closing animation, if there were one, finish before looking.
        ->assertScript('() => Promise.all(document.getAnimations().filter((a) => a.effect?.getTiming().iterations !== Infinity).map((a) => a.finished)).then(() => true)')
        ->assertPresent('[data-test="tenant-blocked-dialog"]')
        ->assertSee('El acceso a Acme Industrial está en pausa');
});

it('takes a person with several companies from the blocked one to the list', function (): void {
    $user = User::factory()->create();
    TenantMembership::factory()->for(Tenant::factory()->deactivated()->create(['slug' => 'acme']))->for($user)->create();
    TenantMembership::factory()->for(Tenant::factory()->create(['slug' => 'beta']))->for($user)->create();
    $this->actingAs($user);

    visit('/admin/acme')
        ->click('Cambiar de empresa')
        ->assertPathIs('/admin');
});
