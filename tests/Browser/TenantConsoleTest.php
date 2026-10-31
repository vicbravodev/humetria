<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;

// The labels of the open row menu, in order.
const MENU_ITEMS = '() => [...document.querySelectorAll(\'[role="menuitem"]\')].map((item) => item.textContent).join(", ")';

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the superadmin every company with its status', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create([
        'trade_name' => 'Grupo Ejemplo',
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'rfc' => 'GEJ0101011A1',
    ]);
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Con owner']))
        ->for(User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']))->create();
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Con invitación']))
        ->create(['name' => 'Beto Ruiz', 'email' => 'beto@acme.mx']);
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Con vencida']))
        ->expired()->create(['name' => 'Carla Soto', 'email' => 'carla@acme.mx']);
    $both = Tenant::factory()->create(['trade_name' => 'Con las dos']);
    TenantMembership::factory()->for($both)->for(User::factory()->create(['name' => 'Dora Luna', 'email' => 'dora@acme.mx']))->create();
    TenantInvitation::factory()->for($both)->create(['name' => 'Eva Mora', 'email' => 'eva@acme.mx']);

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile)
        ->assertSee('Empresas')
        ->assertSee('5 empresas')
        ->assertSee('Sin invitar')
        ->assertSee('Ana Pérez')
        ->assertSee('ana@acme.mx')
        ->assertSee('Invitación enviada · vence el')
        ->assertSee('Vencida')
        ->assertSee('carla@acme.mx')
        ->assertSee('dora@acme.mx')
        ->assertSee('eva@acme.mx')
        ->assertSee('Grupo Ejemplo')
        ->assertSee('Grupo Ejemplo S.A. de C.V.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar')
        // A company with an owner offers to change it, from its row's menu.
        ->click('[aria-label="Acciones de Con owner"]')
        ->assertSee('Cambiar owner');
})->with($looks);

it('keeps the actions of each row in one menu, so the table fits without scrolling sideways', function (): void {
    signInAsSuperadmin();
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']))->create();

    visit('/admin/companies')
        ->resize(1280, 800)
        ->assertScript('() => { const box = document.querySelector(\'[data-slot="table-container"]\'); return box.scrollWidth <= box.clientWidth; }')
        ->assertScript('() => document.querySelectorAll(\'[data-test="tenant-actions"]\').length', 1)
        ->click('[aria-label="Acciones de Grupo Ejemplo"]')
        ->assertScript(MENU_ITEMS, 'Activar, Invitar owner, Editar, Logo de los correos, Desactivar')
        // Desactivar is apart from the rest and in the destructive color.
        ->assertScript('() => document.querySelector(\'[role="menuitem"][data-variant="destructive"]\')?.textContent', 'Desactivar')
        ->assertScript('() => document.querySelector(\'[role="menu"] [role="separator"]\') !== null');
});

it('says so when there are no companies yet', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('tenancy/index', '/admin/companies', $dark)
        ->assertSee('Aún no hay empresas');
})->with(['light' => false, 'dark' => true]);

it('links the console from the sidebar only for the superadmin', function (): void {
    signInAsSuperadmin();

    // /admin itself leads the superadmin to the console, so the link is checked from another page.
    visit('/admin/settings/appearance')
        ->assertPresent('[data-sidebar="menu-button"][href$="/admin/companies"]')
        ->click('Empresas')
        ->assertPathIs('/admin/companies');

    $panel = signInToPanel();

    visit($panel)
        ->assertMissing('a[href$="/admin/companies"]');
});

it('marks the sidebar as the console only for the superadmin outside a company', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->assertSeeIn('[data-slot="sidebar-header"]', 'Consola');

    $panel = signInToPanel();

    visit($panel)
        ->assertDontSeeIn('[data-slot="sidebar-header"]', 'Consola');
});

it('gives the superadmin no Panel outside a company: Empresas is where they start', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->assertPresent('[data-sidebar="menu-button"][href$="/admin/companies"]')
        ->assertScript('() => [...document.querySelectorAll(\'[data-sidebar="menu-button"]\')].every((link) => link.textContent.trim() !== "Panel")');
});

it('creates a company from the dialog, suggesting its address from the trade name', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->assertSee('La empresa queda sin iniciar: su prueba de 14 días empieza cuando su responsable entre por primera vez.')
        ->type('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->type('rfc', 'gej0101011a1')
        ->type('legal_name', 'Grupo Ejemplo S.A. de C.V.')
        ->press('Dar de alta')
        ->assertSee('Empresa dada de alta.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->slug)->toBe('grupo-ejemplo');
});

it('accepts an RFC typed with spaces', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('trade_name', 'Grupo Ejemplo')
        ->type('rfc', 'GOMA 840229 AB3')
        ->type('legal_name', 'Grupo Ejemplo S.A. de C.V.')
        ->press('Dar de alta')
        ->assertSee('Empresa dada de alta.')
        ->assertSee('GOMA840229AB3');

    expect(Tenant::query()->sole()->rfc)->toBe('GOMA840229AB3');
});

it('stops following the trade name once the address has been edited', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('slug', 'mi-direccion')
        ->type('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'mi-direccion');
});

it('shows what is wrong in the dialog and keeps it open', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['rfc' => 'GEJ0101011A1']);

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->type('trade_name', 'Grupo Ejemplo')
        ->type('legal_name', 'Grupo Ejemplo S.A. de C.V.')
        ->type('rfc', 'GEJ0101011A1')
        ->press('Dar de alta')
        ->assertSee('Ya hay una empresa con este RFC.');

    expect(Tenant::query()->count())->toBe(1);
});

it('passes the accessibility and CSP checks with the dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Dar de alta empresa')->assertSee('Razón social'));
})->with($looks);

it('edits the legal name from the dialog and shows it in the row', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo', 'legal_name' => 'Grupo Ejemplo S.A. de C.V.', 'slug' => 'grupo-ejemplo']);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Editar')
        ->assertSee('Editar empresa')
        ->assertValue('trade_name', 'Grupo Ejemplo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->type('legal_name', 'Grupo Ejemplo Corregido S.A.')
        ->type('trade_name', 'Grupo Nuevo')
        ->assertValue('slug', 'grupo-ejemplo')
        ->assertDontSee('Las direcciones anteriores')
        ->press('Guardar')
        ->assertSee('Empresa actualizada.')
        ->assertSee('Grupo Ejemplo Corregido S.A.')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->slug)->toBe('grupo-ejemplo');
});

it('warns that the old addresses stop working when the address is edited', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['slug' => 'grupo-ejemplo']);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Editar')
        ->type('slug', 'otra-direccion')
        ->assertSee('Las direcciones anteriores de esta empresa dejarán de funcionar.');
});

it('passes the accessibility and CSP checks with the edit dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Editar')->assertSee('Editar empresa'));
})->with($looks);

it('activates a company that has not started, choosing the date', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    // The calendar opens on the month of the first day it allows (tomorrow), not today's: on the last day of a month
    // that is already the next one. Mid-month, two months after it: always two clicks on the next-month arrow.
    $until = now('America/Mexico_City')->addDay()->startOfMonth()->addMonths(2)->addDays(14);

    visit('/admin/companies')
        ->assertSee('Sin iniciar')
        ->click('@tenant-actions')->click('Activar')
        ->assertSee('Activar empresa')
        // The date is picked in a calendar in Spanish, not typed.
        ->assertSeeIn('#tenant-active_until', 'Elige una fecha')
        ->click('#tenant-active_until')
        ->assertVisible('[data-slot="calendar"]')
        ->click('[aria-label="Ir al mes siguiente"]')
        ->click('[aria-label="Ir al mes siguiente"]')
        ->click('[data-day="'.$until->format('Y-m-d').'"] button')
        ->assertMissing('[data-slot="calendar"]')
        ->assertSeeIn('#tenant-active_until', $until->locale('es')->isoFormat('D [de] MMMM [de] YYYY'))
        // The row's own "Activar" button shares the text, so the dialog's submit is picked by its place.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa activa hasta')
        ->assertSee('Activa hasta '.str_replace('.', '', $until->isoFormat('D MMM YYYY')))
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->status()->value)->toBe('active');
});

it('lets no day before tomorrow be picked', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create();
    $today = now('America/Mexico_City');

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Activar')
        ->click('#tenant-active_until')
        // It opens on the first day it allows, with today greyed out and out of reach.
        ->assertScript('() => document.activeElement.closest("[data-day]")?.dataset.day', $today->copy()->addDay()->format('Y-m-d'))
        ->assertScript('() => document.querySelector(\'[data-day="'.$today->format('Y-m-d').'"] button\')?.disabled ?? true');
});

it('passes the accessibility and CSP checks with the calendar open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page
        ->click('@tenant-actions')->click('Activar')
        ->click('#tenant-active_until')
        ->assertVisible('[data-slot="calendar"]'));
})->with($looks);

it('keeps the address prefix inside its field and outlines the whole field on focus', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->click('Dar de alta empresa')
        ->assertScript('() => document.querySelector("#tenant-slug").closest(\'[data-slot="input-group"]\')?.textContent', '/admin/')
        ->keys('#tenant-slug', 'a')
        ->assertScript('() => getComputedStyle(document.querySelector("#tenant-slug").closest(\'[data-slot="input-group"]\')).outlineStyle', 'solid');
});

it('offers to reactivate a blocked company', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->deactivated()->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Reactivar')
        ->assertSee('Reactivar empresa');
});

it('offers to change the end date of an active company instead of activating it again', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create();

    visit('/admin/companies')
        ->click('@tenant-actions')
        ->assertScript(MENU_ITEMS, 'Cambiar vigencia, Invitar owner, Editar, Logo de los correos, Desactivar')
        ->click('Cambiar vigencia')
        ->assertSee('Elige el último día')
        ->assertSeeIn('[role=dialog] button[type=submit]', 'Guardar');
});

it('passes the accessibility and CSP checks with the activation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Activar')->assertSee('Activar empresa'));
})->with($looks);

it('deactivates an active company after confirming, and hides the button afterwards', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Activa hasta')
        ->click('@tenant-actions')->click('Desactivar')
        ->assertSee('¿Desactivar Grupo Ejemplo?')
        ->assertSee('Sus datos se conservan.')
        ->assertDontSee('La prueba no se puede reanudar.')
        // The row's own "Desactivar" button shares the text, so the dialog's submit is picked by its place.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa desactivada.')
        ->assertSee('Desactivada el')
        ->assertDontSee('Desactivar')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->status()->value)->toBe('deactivated');
});

it('warns that a trial cannot be resumed once the company is deactivated', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->inTrial(now())->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Desactivar')
        ->assertSee('La prueba no se puede reanudar.');
});

it('does not offer to deactivate a blocked company', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->deactivated()->create();

    visit('/admin/companies')
        ->click('@tenant-actions')
        ->assertScript(MENU_ITEMS, 'Reactivar, Invitar owner, Editar, Logo de los correos');
});

it('passes the accessibility and CSP checks with the deactivation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Desactivar')->assertSee('Sus datos se conservan.'));
})->with($looks);

it('invites the owner from the console', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Sin invitar')
        ->click('@tenant-actions')->click('Invitar owner')
        ->assertSee('Le enviamos un correo con un enlace, válido por 7 días')
        ->type('name', 'Ana Pérez')
        ->type('email', 'ana@acme.mx')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertSee('ana@acme.mx')
        ->assertDontSee('Sin invitar')
        ->assertNoJavaScriptErrors();

    expect(TenantInvitation::query()->sole()->email)->toBe('ana@acme.mx');
});

it('cancels an invitation from the console', function (): void {
    signInAsSuperadmin();
    $invitation = TenantInvitation::factory()->for(Tenant::factory()->create())->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    visit('/admin/companies')
        ->assertSee('ana@acme.mx')
        ->click('Cancelar')
        ->assertSee('El enlace dejará de funcionar.')
        ->press('Cancelar invitación')
        ->assertSee('Invitación cancelada.')
        ->assertDontSee('ana@acme.mx')
        ->assertSee('Sin invitar')
        ->assertNoJavaScriptErrors();

    expect($invitation->refresh()->cancelled_at)->not->toBeNull();
});

it('resends an invitation from the console', function (): void {
    signInAsSuperadmin();
    $invitation = TenantInvitation::factory()->for(Tenant::factory()->create())->create();
    $before = $invitation->token_hash;

    visit('/admin/companies')
        ->click('Reenviar')
        ->assertSee('Invitación reenviada.')
        ->assertNoJavaScriptErrors();

    expect($invitation->refresh()->token_hash)->not->toBe($before);
});

it('passes the accessibility and CSP checks with the cancel invitation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    TenantInvitation::factory()->for(Tenant::factory()->create())->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Cancelar')->assertSee('El enlace dejará de funcionar.'));
})->with($looks);

it('shows what is wrong in the invitation dialog and keeps it open', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Invitar owner')
        ->type('name', 'Ana Pérez')
        ->type('email', 'no-es-correo')
        ->press('Enviar invitación')
        ->assertSee('El campo correo electrónico debe ser un correo electrónico válido.');

    expect(TenantInvitation::query()->count())->toBe(0);
});

it('passes the accessibility and CSP checks with the invitation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Invitar owner')->assertSee('Enviar invitación'));
})->with($looks);

it('enters as the owner from the console, shows the banner on every page, and comes back with "Salir"', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo', 'slug' => 'grupo-ejemplo']);
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['name' => 'Ana Pérez']))->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Entrar como owner')
        ->assertPathIs('/admin/grupo-ejemplo')
        ->assertSee('Estás viendo Humetría como Ana Pérez · Salir')
        // Settings has its own layout inside the frame: the banner is there too.
        ->navigate('/admin/settings/appearance')
        ->assertSee('Estás viendo Humetría como Ana Pérez')
        ->click('@stop-impersonation')
        ->assertPathIs('/admin/companies')
        ->assertDontSee('Estás viendo Humetría')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('leaves only the appearance settings while impersonating', function (): void {
    signInAsSuperadmin();
    TenantMembership::factory()->for(Tenant::factory()->create())->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Entrar como owner')
        ->navigate('/admin/settings/appearance')
        ->assertSee('Apariencia')
        ->assertDontSee('Perfil')
        ->assertDontSee('Seguridad')
        ->assertNoJavaScriptErrors();
});

it('offers to enter as the owner only when the company has one', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visit('/admin/companies')
        ->click('@tenant-actions')
        ->assertDontSee('Entrar como owner');
});

it('passes the accessibility and CSP checks with the impersonation banner', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    TenantMembership::factory()->for(Tenant::factory()->create())->create();

    visitPage('dashboard', '/admin/companies', $dark, $mobile, fn ($page) => $page
        ->click('@tenant-actions')->click('Entrar como owner')
        ->assertSee('Estás viendo Humetría como'));
})->with($looks);

it('narrows the companies by name or RFC and by status', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonth())->create(['trade_name' => 'Autopartes Querétaro']);
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->type('input[type="search"]', 'queretaro')
        ->assertDontSee('Grupo Ejemplo')
        ->assertSee('Autopartes Querétaro')
        ->assertQueryStringHas('q', 'queretaro')
        ->click('a[href*="status=trial"]')
        ->assertSee('Ninguna empresa coincide')
        ->assertNoJavaScriptErrors();
});
