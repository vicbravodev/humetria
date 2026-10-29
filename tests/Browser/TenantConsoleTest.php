<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;

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
        ->assertSee('Ana Pérez · ana@acme.mx')
        ->assertSee('Activo')
        ->assertSee('Invitación enviada')
        ->assertSee('Vence el')
        ->assertSee('Vencida')
        ->assertSee('Carla Soto · carla@acme.mx')
        ->assertSee('Dora Luna · dora@acme.mx')
        ->assertSee('Eva Mora · eva@acme.mx')
        ->assertSee('Cambiar owner')
        ->assertSee('Grupo Ejemplo')
        ->assertSee('Grupo Ejemplo S.A. de C.V.')
        ->assertSee('GEJ0101011A1')
        ->assertSee('Sin iniciar');
})->with($looks);

it('says so when there are no companies yet', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('tenancy/index', '/admin/companies', $dark)
        ->assertSee('Aún no hay empresas');
})->with(['light' => false, 'dark' => true]);

it('links the console from the sidebar only for the superadmin', function (): void {
    signInAsSuperadmin();

    visit('/admin')
        ->click('Empresas')
        ->assertPathIs('/admin/companies');

    $this->actingAs(User::factory()->create());

    visit('/admin')->assertDontSee('Empresas');
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
        ->click('Editar')
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
        ->click('Editar')
        ->type('slug', 'otra-direccion')
        ->assertSee('Las direcciones anteriores de esta empresa dejarán de funcionar.');
});

it('passes the accessibility and CSP checks with the edit dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Editar')->assertSee('Editar empresa'));
})->with($looks);

it('activates a company that has not started, choosing the date', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);
    // Mid-month, two months ahead: always reachable with two clicks on the next-month arrow.
    $until = now('America/Mexico_City')->startOfMonth()->addMonths(2)->addDays(14);

    visit('/admin/companies')
        ->assertSee('Sin iniciar')
        ->click('Activar')
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
        ->click('Activar')
        ->click('#tenant-active_until')
        // It opens on the first day it allows, with today greyed out and out of reach.
        ->assertScript('() => document.activeElement.closest("[data-day]")?.dataset.day', $today->copy()->addDay()->format('Y-m-d'))
        ->assertScript('() => document.querySelector(\'[data-day="'.$today->format('Y-m-d').'"] button\')?.disabled ?? true');
});

it('passes the accessibility and CSP checks with the calendar open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page
        ->click('Activar')
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
        ->click('Reactivar')
        ->assertSee('Reactivar empresa');
});

it('offers to change the end date of an active company instead of activating it again', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create();

    visit('/admin/companies')
        ->assertMissing('button[aria-label^="Activar "]')
        ->click('Cambiar vigencia')
        ->assertSee('Elige el último día')
        ->assertSeeIn('[role=dialog] button[type=submit]', 'Guardar');
});

it('passes the accessibility and CSP checks with the activation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Activar')->assertSee('Activar empresa'));
})->with($looks);

it('deactivates an active company after confirming, and hides the button afterwards', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->activeUntil(now()->addMonths(2))->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Activa hasta')
        ->click('Desactivar')
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
        ->click('Desactivar')
        ->assertSee('La prueba no se puede reanudar.');
});

it('does not offer to deactivate a blocked company', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->deactivated()->create();

    visit('/admin/companies')->assertDontSee('Desactivar');
});

it('passes the accessibility and CSP checks with the deactivation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Desactivar')->assertSee('Sus datos se conservan.'));
})->with($looks);

it('invites the owner from the console', function (): void {
    signInAsSuperadmin();
    Tenant::factory()->create(['trade_name' => 'Grupo Ejemplo']);

    visit('/admin/companies')
        ->assertSee('Sin invitar')
        ->click('Invitar owner')
        ->assertSee('Le enviamos un correo con un enlace, válido por 7 días')
        ->type('name', 'Ana Pérez')
        ->type('email', 'ana@acme.mx')
        ->press('Enviar invitación')
        ->assertSee('Invitación enviada.')
        ->assertSee('Ana Pérez · ana@acme.mx')
        ->assertDontSee('Sin invitar')
        ->assertNoJavaScriptErrors();

    expect(TenantInvitation::query()->sole()->email)->toBe('ana@acme.mx');
});

it('cancels an invitation from the console', function (): void {
    signInAsSuperadmin();
    $invitation = TenantInvitation::factory()->for(Tenant::factory()->create())->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    visit('/admin/companies')
        ->assertSee('Ana Pérez · ana@acme.mx')
        ->click('Cancelar')
        ->assertSee('El enlace dejará de funcionar.')
        ->press('Cancelar invitación')
        ->assertSee('Invitación cancelada.')
        ->assertDontSee('Ana Pérez · ana@acme.mx')
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
        ->click('Invitar owner')
        ->type('name', 'Ana Pérez')
        ->type('email', 'no-es-correo')
        ->press('Enviar invitación')
        ->assertSee('El campo correo electrónico debe ser un correo electrónico válido.');

    expect(TenantInvitation::query()->count())->toBe(0);
});

it('passes the accessibility and CSP checks with the invitation dialog open', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    Tenant::factory()->create();

    visitPage('tenancy/index', '/admin/companies', $dark, $mobile, fn ($page) => $page->click('Invitar owner')->assertSee('Enviar invitación'));
})->with($looks);
