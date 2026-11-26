<?php

declare(strict_types=1);

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

it('says there is no announcement yet', function (): void {
    signInAsSuperadmin();

    visitPage('announcements/index', '/admin/announcements')
        ->assertSee('No hay avisos programados');
});
