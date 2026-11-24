<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Spatie\Permission\Models\Role;

// The critical path of roles of a company's own: the owner makes one with a single permission and gives it to someone, who then
// sees only that and finds no link that answers 403.
it('lets the owner make a role with only the vacations, give it to someone, and that person sees only the vacations', function (): void {
    signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $person = User::factory()->create(['name' => 'Vale Vacaciones']);
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();

    visit('/admin/acme/roles')
        ->click('Nuevo rol')
        ->type('label', 'Solo vacaciones')
        ->click('@permission-view-vacations')
        ->press('Crear rol')
        ->assertSee('Rol creado.')
        ->assertSee('Solo vacaciones')
        ->assertNoJavaScriptErrors();

    $role = Role::query()->where('label', 'Solo vacaciones')->sole();
    expect($role->permissions->pluck('name')->all())->toBe(['view-vacations']);

    visit('/admin/acme/members')
        ->click('@member-role')->click('[role="option"]:has-text("Solo vacaciones")')
        ->assertSee('Rol actualizado.')
        ->assertNoJavaScriptErrors();

    expect(resolve(CompanyRoles::class)->roleOf($person, $tenant)?->is($role))->toBeTrue();

    $this->actingAs($person);

    // They enter as anyone does, at /admin, and land on the first page they may open.
    visit('/admin')
        ->assertScript(pathBecomes('/admin/acme/vacations'), true)
        ->assertSeeIn('[data-sidebar="content"]', 'Vacaciones')
        ->assertDontSeeIn('[data-sidebar="content"]', 'Panel')
        ->assertDontSeeIn('[data-sidebar="content"]', 'Centros de trabajo')
        ->assertDontSeeIn('[data-sidebar="content"]', 'NOM-035')
        ->assertDontSeeIn('[data-sidebar="content"]', 'Usuarios')
        // Every link of the menu, the logo's included, opens: none answers 403 (nor anything but 200).
        ->assertScript(<<<'JS'
            async () => {
                const links = [...new Set([...document.querySelectorAll('[data-sidebar="sidebar"] a[href]')].map((link) => link.href))];
                const statuses = await Promise.all(links.map((url) => fetch(url, { redirect: 'follow' }).then((response) => response.status)));

                return links.length > 0 && statuses.every((status) => status === 200);
            }
            JS, true)
        ->assertNoJavaScriptErrors();
});
