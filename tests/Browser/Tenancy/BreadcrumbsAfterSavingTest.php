<?php

declare(strict_types=1);

// A form that saves and lands on another page must not leave the previous page's breadcrumbs in the top bar.
it('shows the members\' place after inviting someone, not the invitation page\'s', function (): void {
    signInToPanel();

    visit('/admin/acme/members')
        ->click('[data-tour="invite-member"]')
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Invitar')
        ->type('name', 'Nora Nueva')
        ->type('email', 'nora@example.com')
        ->press('Enviar invitación')
        ->assertSee('Nora Nueva')
        ->assertDontSeeIn('nav[aria-label="Ruta"]', 'Invitar')
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Usuarios')
        ->assertNoJavaScriptErrors();
});

it('shows the roles\' place after saving or deleting a role, not the editor\'s', function (): void {
    signInToPanel();

    visit('/admin/acme/roles/create')
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Nuevo rol')
        ->type('label', 'Solo migas')
        ->click('@level-vacations-view')
        ->press('Crear rol')
        ->assertSee('Rol creado.')
        ->assertDontSeeIn('nav[aria-label="Ruta"]', 'Solo migas')
        ->assertDontSeeIn('nav[aria-label="Ruta"]', 'Nuevo rol')
        ->assertNoJavaScriptErrors();
});
