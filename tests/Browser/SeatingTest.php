<?php

declare(strict_types=1);

use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** @return array{string, WorkCenter} The panel's address and a work center of the company the user signed in to. */
function seatingCenter(): array
{
    $url = signInToPanel();

    return [$url, WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro', 'main_activity' => 'Manufactura'])];
}

it('lists the centers of the company with their declared sector', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    SeatingProfile::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'sector' => 'commerce']);

    visitPage('seating/index', "{$url}/seating", $dark, $mobile)
        ->assertSee('Ley Silla')
        ->assertSee('Planta Querétaro')
        ->assertSee('Comercio')
        ->assertSee('Abrir centro');
})->with($looks);

it('shows a center with its declaration form', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/center', "{$url}/seating/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Planta Querétaro')
        ->assertSee('Sector del centro')
        ->assertSee('Guardar declaración');
})->with($looks);

it('declares the sector of a center from the page', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}")
        ->click('Servicios')
        ->press('Guardar declaración')
        ->assertSee('Declaración guardada.')
        ->assertSee('Declarado el')
        ->assertNoJavaScriptErrors();
});
