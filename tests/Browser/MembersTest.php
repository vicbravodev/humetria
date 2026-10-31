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
        ->assertSee('Pendiente')
        ->assertSee('Qué puede hacer cada rol');
})->with($looks);

it('passes the checks with the invite dialog and a row menu open', function (bool $dark, bool $mobile): void {
    acmeWithPeople();

    visitPage('tenancy/members', '/admin/acme/members', $dark, $mobile, fn (AwaitableWebpage $page) => $page
        ->click('Invitar')
        ->assertSee('Le llegará un correo con un enlace de un solo uso, válido por 7 días.'));

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

    expect(TenantInvitation::query()->where('email', 'diego@acme.mx')->sole()->role)->toBe(TenantRole::Viewer);
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
    $page->script('() => { const send = XMLHttpRequest.prototype.send; XMLHttpRequest.prototype.send = function (...args) { setTimeout(() => send.apply(this, args), 400); }; }');
    $page->click('@member-role')->click('[role="option"]:has-text("Consulta")')
        ->assertSeeIn('@member-role', 'Consulta')
        ->assertAttribute('@member-role', 'aria-busy', 'true')
        ->assertScript('() => document.querySelector(\'[data-test="member-role"]\').disabled', true)
        ->assertSee('Rol actualizado.')
        ->assertScript('() => document.querySelector(\'[data-test="member-role"]\').disabled', false)
        ->assertNoJavaScriptErrors();
});

it('changes a role and takes access away', function (): void {
    $tenant = acmeWithPeople();

    visit('/admin/acme/members')
        ->click('@member-role')->click('[role="option"]:has-text("Consulta")')
        ->assertSee('Rol actualizado.')
        ->assertSeeIn('@member-role', 'Consulta')
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
        ->assertPathIs('/admin/acme/members');
});
