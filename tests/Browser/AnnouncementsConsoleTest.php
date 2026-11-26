<?php

declare(strict_types=1);

use App\Modules\Announcements\Actions\StartMaintenance;
use App\Modules\Announcements\Models\Announcement;

it('lists the announcements to come and running, and opens the dialog of a new one', function (bool $dark, bool $mobile): void {
    $this->travelTo('2026-11-26 15:00:00');
    signInAsSuperadmin();
    Announcement::factory()->create(['title' => 'Mantenimiento programado', 'kind' => 'maintenance', 'starts_at' => '2026-11-29 04:00', 'ends_at' => '2026-11-29 07:00']);
    Announcement::factory()->forPlan('integral')->create(['title' => 'Nueva plantilla de reporte STPS']);

    visitPage('announcements/index', '/admin/announcements', $dark, $mobile)
        ->assertSee('Avisos y mantenimiento')
        ->assertSee('Mantenimiento programado')
        ->assertSee('Programado')
        ->assertSee('Plan Integral')
        ->assertSee('Activo');

    // The dialog is checked once it has settled, like any page.
    visitPage('announcements/index', '/admin/announcements', $dark, $mobile, fn ($page) => $page->click('Nuevo aviso'))
        ->assertSee('Mensaje para las empresas');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);

it('writes the announcement in the page and shows it as the company will see it', function (): void {
    signInAsSuperadmin();

    visitPage('announcements/index', '/admin/announcements', reach: fn ($page) => $page->type('message', 'Esta noche haremos mantenimiento.'))
        ->assertSee('Así lo verá la empresa')
        ->assertSeeIn('[data-slot="announcement-preview"]', 'Esta noche haremos mantenimiento.')
        ->assertSee('Programar aviso')
        ->assertSee('Guardar borrador');
});

it('loads an announcement into the form to change it', function (): void {
    signInAsSuperadmin();
    Announcement::factory()->create(['title' => 'Nueva plantilla de reporte STPS', 'message' => 'Ya puedes descargarla.']);

    visitPage('announcements/index', '/admin/announcements', reach: fn ($page) => $page->click('[aria-label="Editar «Nueva plantilla de reporte STPS»"]'))
        ->assertSee('Editar aviso')
        ->assertValue('title', 'Nueva plantilla de reporte STPS')
        ->assertSeeIn('[data-slot="announcement-preview"]', 'Ya puedes descargarla.');
});

it('leaves the change for a blank form when «Nuevo aviso» is pressed', function (): void {
    signInAsSuperadmin();
    Announcement::factory()->create(['title' => 'Nueva plantilla de reporte STPS']);

    visitPage('announcements/index', '/admin/announcements', reach: fn ($page) => $page->click('[aria-label="Editar «Nueva plantilla de reporte STPS»"]')->click('a[href="#nuevo-aviso"]'))
        ->assertDontSee('Editar aviso')
        ->assertValue('title', '');
});

it('saves the change as a draft from the form in the page, and leaves the form blank again', function (): void {
    signInAsSuperadmin();
    $announcement = Announcement::factory()->create(['title' => 'Nueva plantilla de reporte STPS']);

    visitPage('announcements/index', '/admin/announcements', reach: fn ($page) => $page->click('[aria-label="Editar «Nueva plantilla de reporte STPS»"]')->clear('title')->type('title', 'Reporte STPS nuevo')->click('Guardar borrador'))
        ->assertSee('Reporte STPS nuevo')
        ->assertSee('Nuevo aviso')
        ->assertValue('title', '');

    expect($announcement->refresh())->title->toBe('Reporte STPS nuevo')->published_at->toBeNull();
});

it('says there is no announcement yet', function (): void {
    signInAsSuperadmin();

    visitPage('announcements/index', '/admin/announcements')
        ->assertSee('No hay avisos programados');
});

it('asks for the reason and the word before the maintenance, and offers to end it while it runs', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();

    visitPage('announcements/index', '/admin/announcements', $dark, $mobile, fn ($page) => $page->click('Activar mantenimiento'))
        ->assertSee('Escribe «MANTENIMIENTO» para confirmar');

    resolve(StartMaintenance::class)->handle('Migración de la base de datos');

    visitPage('announcements/index', '/admin/announcements', $dark, $mobile)
        ->assertSee('Migración de la base de datos')
        ->assertSee('Terminar mantenimiento');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);
