<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\AttendancePolicy;
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

    $page = visitPage('tenancy/index', '/admin/companies', $dark, $mobile)
        ->assertSee('Empresas')
        ->assertSee('5 empresas')
        ->assertSee('Grupo Ejemplo')
        ->assertSee('Grupo Ejemplo S.A. de C.V.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar');

    $page->assertSee('Sin invitar')
        ->assertSee('Ana Pérez')
        ->assertSee('ana@acme.mx')
        ->assertSee('Invitación enviada · vence el')
        ->assertSee('Vencida')
        ->assertSee('carla@acme.mx')
        ->assertSee('dora@acme.mx')
        ->assertSee('eva@acme.mx');

    // A company with an owner offers to change it, from its row's menu.
    $page->click('[aria-label="Acciones de Con owner"]')
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
        ->assertScript(MENU_ITEMS, 'Activar, Invitar owner, Editar, Logo de los correos, Asistente Humi, Solo lectura, Suspender, Archivar')
        // Suspender is apart from the rest and in the destructive color (Solo lectura is the mild one).
        ->assertScript('() => document.querySelector(\'[role="menuitem"][data-variant="destructive"]\')?.textContent', 'Suspender')
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
        ->click('Plataforma')
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
        ->assertSee('Dirección web')
        ->assertSee('Así se verá:')
        ->assertSee('/admin/grupo-ejemplo')
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

// C4: the logo is dropped on the zone or chosen from it.
it('passes the accessibility and CSP checks with the logo dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Logo de los correos')
        ->assertSee('Arrastra aquí el logo o elígelo')
        ->assertSee('PNG, JPG o WebP de hasta 2 MB'));
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

it('offers to change the end date of an active company instead of activating it again', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create();

    visit('/admin/companies')
        ->click('@tenant-actions')
        ->assertScript(MENU_ITEMS, 'Cambiar vigencia, Invitar owner, Editar, Logo de los correos, Asistente Humi, Solo lectura, Suspender, Archivar')
        ->click('Cambiar vigencia')
        ->assertSee('Elige el último día')
        ->assertSeeIn('[role=dialog] button[type=submit]', 'Guardar');
});

it('passes the accessibility and CSP checks with the activation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Activar')->assertSee('Activar empresa'));
})->with($looks);

it('suspends an active company after showing what changes and asking the reason, and offers to reactivate it afterwards', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Activa hasta')
        ->click('@tenant-actions')->click('Suspender')
        ->assertSee('Suspender a Grupo Ejemplo')
        ->assertSee('Nadie de la empresa entra al panel')
        ->assertSee('El saldo no cambia')
        ->assertSee('Nunca se borran')
        ->fill('reason', 'Falta de pago de octubre')
        // The row's own menu shares the text, so the dialog's submit is picked by its place.
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa suspendida.')
        ->assertSee('Suspendida el')
        ->assertSee('Motivo: Falta de pago de octubre')
        ->click('@tenant-actions')
        ->assertScript(MENU_ITEMS, 'Reactivar, Invitar owner, Editar, Logo de los correos, Asistente Humi, Solo lectura, Archivar')
        ->assertNoJavaScriptErrors();

    $tenant = Tenant::query()->sole();

    expect($tenant->status()->value)->toBe('suspended')
        ->and($tenant->restriction_reason)->toBe('Falta de pago de octubre');
});

it('warns, before restricting a company that records working time, that it must do it another way', function (): void {
    signInAsSuperadmin();
    AttendancePolicy::factory()->create(['tenant_id' => Tenant::factory()->activeUntil(now()->addMonths(2))->create(['trade_name' => 'Con jornada'])->id]);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Suspender')
        ->assertSee('Esta empresa registra la jornada con Humetría')
        ->assertSee('debe registrar la jornada por otro medio')
        ->assertSee('Esto no la exime de esa obligación');
});

it('says nothing of working time to a company that does not use Attendance', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Suspender')
        ->assertSee('Nunca se borran')
        ->assertDontSee('registra la jornada');
});

it('shows support the console and the way into a company, and nothing that changes one', function (bool $dark, bool $mobile): void {
    $this->actingAs(support());
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Con owner']))
        ->for(User::factory()->create(['name' => 'Ana Pérez']))->create();
    Tenant::factory()->create(['trade_name' => 'Sin owner']);

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile)
        ->assertSee('Con owner')
        ->assertSee('Entrar como owner')
        ->assertDontSee('Dar de alta empresa')
        ->assertMissing('@tenant-actions')
        ->assertDontSee('Reenviar');
})->with($looks);

it('lets support resend a pending invitation, without the option to cancel it', function (): void {
    $this->actingAs(support());
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Con invitación']))
        ->create(['name' => 'Beto Ruiz', 'email' => 'beto@acme.mx']);

    visitPage('tenancy/index', '/admin/companies')
        ->assertSee('Reenviar')
        ->assertDontSee('Cancelar')
        ->assertMissing('[aria-label="Cancelar invitación de Con invitación"]');
});

it('lets support enter a company as its owner', function (): void {
    $this->actingAs(support());
    TenantMembership::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']))
        ->for(User::factory()->create(['name' => 'Ana Pérez']))->create();

    visit('/admin/companies')
        ->click('Entrar como owner')
        ->assertPathBeginsWith('/admin/acme')
        ->assertSee('Estás viendo Humetría como Ana Pérez')
        ->assertNoJavaScriptErrors();
});

it('does not suspend without a reason', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create();

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Suspender')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Suspender a')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->restriction)->toBeNull();
});

it('reactivates a restricted company with a reason, keeping its dates', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->readOnly()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->click('@tenant-actions')->click('Reactivar')
        ->assertSee('Reactivar a Grupo Ejemplo')
        ->assertSee('No reinicia la prueba ni la vigencia')
        ->fill('reason', 'Regularizó su pago')
        ->click('[role=dialog] button[type=submit]')
        ->assertSee('Empresa reactivada.')
        ->assertNoJavaScriptErrors();

    expect(Tenant::query()->sole()->restriction)->toBeNull();
});

it('does not offer the restriction a company already has', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->suspended()->create();

    visit('/admin/companies')
        ->click('@tenant-actions')
        ->assertScript(MENU_ITEMS, 'Reactivar, Invitar owner, Editar, Logo de los correos, Asistente Humi, Solo lectura, Archivar');
});

it('passes the accessibility and CSP checks with the restriction dialogs open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Suspender')->assertSee('Nunca se borran'));
})->with($looks);

it('passes the accessibility and CSP checks with the reactivation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->readOnly()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('@tenant-actions')->click('Reactivar')->assertSee('No reinicia la prueba'));
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
        ->assertSee('Nada coincide con tu búsqueda')
        ->assertSee('Quitar filtros')
        ->assertNoJavaScriptErrors();
});

it('keeps the search and the tabs in view when nothing matches, and offers to clear the filters', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies?q=zzz')
        ->assertSee('Nada coincide con tu búsqueda')
        ->assertSee('Quitar filtros')
        ->assertPresent('input[type="search"]')
        ->assertDontSee('Aún no hay empresas')
        ->click('Quitar filtros')
        ->assertSee('Grupo Ejemplo')
        ->assertNoJavaScriptErrors();
});

it('says there are no companies yet only when there are none', function (): void {
    signInAsSuperadmin();

    visit('/admin/companies')
        ->assertSee('Aún no hay empresas')
        ->assertMissing('input[type="search"]');
});

it('keeps the name, RFC, status and menu of a row in view on a phone, without scrolling sideways', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo', 'rfc' => 'GEJ0101011A1']);

    visitPage('tenancy/index', '/admin/companies', false, true)
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar')
        ->assertPresent('[aria-label="Acciones de Grupo Ejemplo"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        // The table does not scroll inside its frame either: the menu is in reach without sliding it.
        ->assertScript('(() => { const frame = document.querySelector(\'[data-slot="table-container"]\'); return frame.scrollWidth <= frame.clientWidth; })()', true);
});

it('warns, when replacing the owner, who loses access to the company', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Con owner']);
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['name' => 'Ana Pérez']))->create();
    Tenant::factory()->create(['trade_name' => 'Sin owner']);

    visit('/admin/companies')
        ->click('[aria-label="Acciones de Con owner"]')->click('Cambiar owner')
        ->assertSee('Cuando acepte, Ana Pérez dejará de tener acceso a Con owner.')
        ->assertNoJavaScriptErrors();
});

it('keeps every letter typed while the last search is still answering', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    $page = visit('/admin/companies');
    // Every request waits 400 ms to leave, so the first search is still answering while more is typed.
    $page->script(SLOW_REQUESTS);
    $page->type('input[type="search"]', 'Gru');
    $page->wait(0.5);
    $page->keys('input[type="search"]', ['p', 'o', ' ', 'E']);
    $page->wait(2);

    $page->assertQueryStringHas('q', 'Grupo E')
        ->assertValue('input[type="search"]', 'Grupo E')
        ->assertNoJavaScriptErrors();
});

it('does not undo a status tab clicked while a search waits to go out', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    $page = visit('/admin/companies');
    $page->script(SLOW_REQUESTS);
    $page->type('input[type="search"]', 'Gru');
    $page->click('a[href*="status=suspended"]');
    $page->wait(2);

    // The box says what the list is narrowed by: the search that never went out is dropped.
    $page->assertQueryStringHas('status', 'suspended')
        ->assertValue('input[type="search"]', '')
        ->assertSee('Ninguna empresa coincide')
        ->assertNoJavaScriptErrors();
});

it('shows the superadmin the audit trail, with an entry opening its context', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    $actor = User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);
    App\Models\AuditEntry::query()->forceCreate([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'tenant_id' => $tenant->id,
        'actor_id' => $actor->id,
        'event' => 'campaign.launched',
        'metadata' => ['centro' => 'Planta Norte'],
        'created_at' => now(),
    ]);

    visitPage('tenancy/audit', '/admin/audit', $dark, $mobile)
        ->assertSee('Bitácora')
        ->assertSee('Campaña lanzada')
        ->assertSee('Ana Pérez')
        ->click('[aria-label="Detalle: Campaña lanzada"]')
        ->assertSee('Planta Norte');
})->with($looks);

it('says so when the filters of the audit trail match nothing', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('tenancy/audit', '/admin/audit?family=billing', $dark)
        ->assertSee('Nada coincide con estos filtros')
        ->assertSee('Quitar filtros');
})->with(['light' => false, 'dark' => true]);

it('shows the superadmin the platform users, each with a menu that asks before it acts', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    User::factory()->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx', 'two_factor_confirmed_at' => now(), 'two_factor_secret' => 's', 'two_factor_recovery_codes' => 'c']);

    visitPage('tenancy/users', '/admin/platform-users', $dark, $mobile)
        ->assertSee('Usuarios')
        ->assertSee('Ana Pérez')
        ->assertSee('ana@acme.mx')
        ->assertSee('Superadmin')
        ->click('[aria-label="Acciones de Ana Pérez"]')
        ->assertSee('Quitar verificación en dos pasos')
        ->assertSee('Dar rol de soporte')
        ->click('Quitar verificación en dos pasos')
        ->assertSee('¿Quitar la verificación en dos pasos de Ana Pérez?')
        ->assertSee('ana@acme.mx');
})->with($looks);

it('says so when no user matches the search', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('tenancy/users', '/admin/platform-users?q=zzzz', $dark)
        ->assertSee('Nadie coincide con tu búsqueda');
})->with(['light' => false, 'dark' => true]);

it('shows the superadmin the platform in four figures, each a way into its list', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->count(2)->create();
    Tenant::factory()->suspended()->create();
    App\Modules\Demos\Models\DemoRequest::factory()->create();

    visitPage('tenancy/overview', '/admin/overview', $dark, $mobile)
        ->assertSee('Resumen')
        ->assertSee('Ingreso mensual contratado')
        ->assertSee('Solicitudes de demo')
        ->assertSee('Sin entrar en 30 días')
        ->assertSee('Suspendidas')
        ->assertSee('1 espera respuesta.');
})->with($looks);

it('shows when each company and each user last came in', function (): void {
    signInAsSuperadmin();
    $tenant = Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    TenantMembership::factory()->for($tenant)->for(User::factory()->create(['last_login_at' => '2026-11-10 18:00:00']))->create();

    visitPage('tenancy/index', '/admin/companies')->assertSee('Último acceso')->assertSee('10 nov 2026');
});
