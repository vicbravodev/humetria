<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Notification;
use Pest\Browser\Api\AwaitableWebpage;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

beforeEach(function (): void {
    Notification::fake();
});

/** Signs in the owner of Acme, at /admin/acme, with an hr person and an open viewer invitation. */
function acmeWithPeople(): Tenant
{
    $owner = User::factory()->create(['name' => 'Olga Owner']);
    signInToPanel($owner);
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['name' => 'Beto Ramírez', 'email' => 'beto@acme.mx']))->hr()->create();
    TenantInvitation::factory()->for($tenant)->role(TenantRole::Viewer)->create(['name' => 'Carla Díaz', 'email' => 'carla@acme.mx']);

    return $tenant;
}

it('lists who has access and the pending invitations', function (bool $dark, bool $mobile): void {
    acmeWithPeople();

    visitPage('tenancy/members', '/admin/acme/members', $dark, $mobile)
        ->assertSee('Olga Owner')
        ->assertSee('(tú)')
        ->assertSee('Beto Ramírez')
        ->assertSee('Carla Díaz')
        ->assertSee('Pendiente');
})->with($looks);

it('invites from its own page, with a summary of what the chosen role and extras let do beside the form', function (bool $dark, bool $mobile): void {
    acmeWithPeople();

    visitPage('tenancy/invite-member', '/admin/acme/members/invitations/create', $dark, $mobile)
        ->assertSee('Invitar a alguien')
        ->assertSee('Qué hará en Humetría')
        ->assertSee('Ayuda con las rutas')
        ->assertSee('Permisos adicionales')
        ->assertSee('Resumen')
        ->assertDontSee('Qué puede hacer cada quien')
        ->assertSee('Le llegará un correo con un enlace de un solo uso, válido por 7 días.');
})->with($looks);

it('says in the summary what the invited person will be able to do, as the role and the extras change', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members/invitations/create')
        ->assertSeeIn('@access-sentence', 'Quien acepte la invitación')
        ->radio('role', 'viewer')
        ->assertSeeIn('@access-sentence', 'Quien acepte la invitación ve')
        ->assertDontSeeIn('@access-sentence', 'administra Vacaciones')
        ->click('@invite-extras')
        ->click('@level-vacations-manage')
        ->assertSeeIn('@access-sentence', 'administra Vacaciones')
        ->assertNoJavaScriptErrors();
});

it('passes the checks with a row menu open', function (bool $dark, bool $mobile): void {
    acmeWithPeople();

    visitPage('tenancy/members', '/admin/acme/members', $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('@member-actions')
        ->assertSee('Quitar acceso'));
})->with($looks);

it('invites someone as a viewer', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members')
        ->click('Invitar')
        ->type('name', 'Diego Luna')
        ->type('email', 'diego@acme.mx')
        ->radio('role', 'viewer')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertSee('Diego Luna')
        ->assertNoJavaScriptErrors();

    expect(TenantInvitation::query()->where('email', 'diego@acme.mx')->sole()->role?->name)->toBe(TenantRole::Viewer->value);
});

it('invites someone to the Comisión de seguridad e higiene', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members')
        ->click('Invitar')
        ->type('name', 'Cora Comisión')
        ->type('email', 'cora@acme.mx')
        ->radio('role', 'committee')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertSee('Cora Comisión')
        ->assertNoJavaScriptErrors();

    expect(TenantInvitation::query()->where('email', 'cora@acme.mx')->sole()->role?->name)->toBe(TenantRole::Committee->value);
});

it('says when the person already has access', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members')
        ->click('Invitar')
        ->type('name', 'Beto')
        ->type('email', 'beto@acme.mx')
        ->press('Enviar invitación')
        ->assertSee('Esa persona ya tiene acceso a esta empresa.');
});

it('shows the role being saved, and holds the field until it is', function (): void {
    acmeWithPeople();

    $page = visit('/admin/acme/members');
    // Every request waits 400 ms to leave, so the save is still under way when the field is read.
    $page->script(SLOW_REQUESTS);
    $page->click('@member-role')->click('[role="option"]:has-text("Solo consulta")')
        ->assertSeeIn('@member-role', 'Solo consulta')
        ->assertAttribute('@member-role', 'aria-busy', 'true')
        ->assertScript('() => document.querySelector(\'[data-test="member-role"]\').disabled', true)
        ->assertSee('Rol actualizado.')
        ->assertScript('() => document.querySelector(\'[data-test="member-role"]\').disabled', false)
        ->assertNoJavaScriptErrors();
});

it('changes a role and takes access away', function (): void {
    $tenant = acmeWithPeople();

    visit('/admin/acme/members')
        ->click('@member-role')->click('[role="option"]:has-text("Solo consulta")')
        ->assertSee('Rol actualizado.')
        ->assertSeeIn('@member-role', 'Solo consulta')
        ->click('@member-actions')->click('Quitar acceso')
        ->assertSee('¿Quitar el acceso de Beto Ramírez?')
        ->press('Quitar acceso')
        ->assertSee('Acceso quitado.')
        ->assertDontSee('Beto Ramírez')
        ->assertNoJavaScriptErrors();

    expect($tenant->memberships()->count())->toBe(1);
});

it('resends and cancels an invitation from its row', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members')
        ->click('[aria-label="Reenviar la invitación de Carla Díaz"]')
        ->assertSee('Invitación reenviada.')
        ->click('[aria-label="Cancelar la invitación de Carla Díaz"]')
        ->assertSee('Invitación cancelada.')
        ->assertDontSee('Carla Díaz')
        ->assertNoJavaScriptErrors();
});

it('shows Usuarios in the sidebar only to the owner', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($tenant, TenantRole::Hr));

    visit('/admin/acme')
        ->assertSee('Centros de trabajo')
        ->assertDontSee('Usuarios');

    $this->actingAs(ownerOf($tenant));

    visit('/admin/acme')
        ->click('Usuarios')
        ->assertScript(pathBecomes('/admin/acme/members'), true)
        ->assertPathIs('/admin/acme/members');
});

it('says how many permissions a person has besides their role, and leads to them', function (): void {
    $tenant = acmeWithPeople();
    $beto = User::query()->where('email', 'beto@acme.mx')->sole();
    resolve(App\Modules\Tenancy\Support\CompanyRoles::class)->syncDirectPermissions($beto, $tenant, ['manage-vacations']);

    visit('/admin/acme/members')
        ->assertSee('+1 permiso adicional')
        ->click('@member-extras')
        ->assertSee('Permisos adicionales de Beto Ramírez')
        ->assertNoJavaScriptErrors();
});

it('invites someone with a role of the company\'s own and a sensitive permission besides it, after confirming', function (): void {
    $tenant = acmeWithPeople();
    $role = app(App\Modules\Tenancy\Actions\CreateCompanyRole::class)->handle($tenant, 'Auditoría', ['view-dashboard', 'view-vacations'], null, User::query()->where('name', 'Olga Owner')->sole());

    visit('/admin/acme/members/invitations/create')
        ->type('name', 'Iris Interna')
        ->type('email', 'iris@acme.mx')
        ->radio('role', $role->name)
        ->assertSee('Rol propio de tu empresa')
        ->click('@invite-extras')
        // The role's levels are the floor: Auditoría already sees the vacations.
        ->assertChecked('input[name="level-vacations"][value="view"]')
        ->assertAttribute('input[name="level-vacations"][value="none"]', 'disabled', '')
        ->click('@extra-view-trauma-screenings')
        ->assertSee('Lo que tendrá además de su rol · 1 área')
        ->assertDontSee('1 áreas')
        ->assertSee('Estás abriendo información sensible')
        ->click('#acknowledge-sensitive')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertSee('Iris Interna')
        ->assertNoJavaScriptErrors();

    $invitation = TenantInvitation::query()->where('email', 'iris@acme.mx')->sole();
    expect($invitation->role?->name)->toBe($role->name)
        ->and($invitation->extra_permissions)->toBe(['view-trauma-screenings']);
});

it('keeps a raised area whole when the role of the invitation changes to one without it', function (): void {
    acmeWithPeople();

    visit('/admin/acme/members/invitations/create')
        ->type('name', 'Raúl Rol')
        ->type('email', 'raul@acme.mx')
        ->radio('role', 'viewer')
        ->click('@invite-extras')
        ->click('@level-vacations-manage')
        ->radio('role', 'complaints')
        ->assertChecked('input[name="level-vacations"][value="manage"]')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertNoJavaScriptErrors();

    expect(TenantInvitation::query()->where('email', 'raul@acme.mx')->sole()->extra_permissions)
        ->toEqualCanonicalizing(['view-vacations', 'manage-vacations']);
});

it('fits a person with extra permissions in a phone: the role and the «+N» link are whole, nothing scrolls sideways', function (bool $dark): void {
    $tenant = acmeWithPeople();
    $beto = User::query()->where('email', 'beto@acme.mx')->sole();
    resolve(App\Modules\Tenancy\Support\CompanyRoles::class)->syncDirectPermissions($beto, $tenant, ['manage-vacations']);

    visitPage('tenancy/members', '/admin/acme/members', $dark, true)
        ->assertSee('+1 permiso adicional')
        ->assertScript(<<<'JS'
            () => {
                const box = (selector) => document.querySelector(selector).getBoundingClientRect();
                const frame = document.querySelector('[data-slot="table-container"]');
                const inside = ['[data-test="member-extras"]', '[data-test="member-role"]', '[data-test="member-actions"]']
                    .every((selector) => box(selector).left >= 0 && box(selector).right <= window.innerWidth);

                return inside && frame.scrollWidth <= frame.clientWidth && document.documentElement.scrollWidth <= window.innerWidth;
            }
            JS, true);
})->with([[false], [true]]);
