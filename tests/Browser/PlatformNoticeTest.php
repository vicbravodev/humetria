<?php

declare(strict_types=1);

use App\Modules\Announcements\Actions\StartMaintenance;
use App\Modules\Announcements\Models\Announcement;
use App\Modules\Announcements\Support\LiveNotices;
use App\Modules\Tenancy\Models\Tenant;

it('shows the platform announcement in the frame of the panel, and a person can close it for good', function (string $kind, bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(ownerOf($tenant));
    Announcement::factory()->create(['kind' => $kind, 'message' => 'Esta noche haremos mantenimiento de 10:00 p. m. a 1:00 a. m.']);
    resolve(LiveNotices::class)->refresh();

    visitPage('dashboard', '/admin/acme', $dark, $mobile)
        ->assertSeeIn('[data-slot="platform-notice"]', 'Esta noche haremos mantenimiento')
        ->click('[aria-label="Cerrar aviso"]')
        ->assertMissing('[data-slot="platform-notice"]')
        ->refresh()
        ->assertSee('Hola')
        ->assertMissing('[data-slot="platform-notice"]');
})->with([
    'a maintenance, light desktop' => ['maintenance', false, false],
    'a maintenance, dark desktop' => ['maintenance', true, false],
    'a novelty, light mobile' => ['news', false, true],
    'a novelty, dark mobile' => ['news', true, true],
]);

it('tells every company the platform is in maintenance, with no way to close it, and hides what would save', function (bool $dark, bool $mobile): void {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(ownerOf($tenant));
    resolve(StartMaintenance::class)->handle('Migración de la base de datos');

    visitPage('dashboard', '/admin/acme', $dark, $mobile)
        ->assertSeeIn('[data-slot="platform-notice"]', 'Humetría está en mantenimiento')
        ->assertMissing('[aria-label="Cerrar aviso"]')
        ->assertDontSee('Migración de la base de datos');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);
