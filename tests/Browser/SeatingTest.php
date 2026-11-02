<?php

declare(strict_types=1);

use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;

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

it('lists the standing positions of a center with their result', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Cajera']);

    visitPage('seating/center', "{$url}/seating/centers/{$center->id}", $dark, $mobile)
        ->assertSee('Puestos de pie')
        ->assertSee('Cajera')
        ->assertSee('Resultado A')
        ->assertSee('Nuevo puesto');
})->with($looks);

it('shows the form of a new position with the questions of Figure 1', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();

    visitPage('seating/position', "{$url}/seating/centers/{$center->id}/positions/create", $dark, $mobile)
        ->assertSee('Nuevo puesto')
        ->assertSee('Figura 1: dónde va el asiento')
        ->assertSee('Guardar puesto');
})->with($looks);

it('shows a saved position with its result and its people', function (bool $dark, bool $mobile): void {
    [$url, $center] = seatingCenter();
    $position = StandingPosition::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Cajera']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $ana->id, 'position_id' => $position->id]);

    visitPage('seating/position', "{$url}/seating/positions/{$position->id}", $dark, $mobile)
        ->assertSee('Resultado A')
        ->assertSee('Se recomienda documentar todas las medidas de cumplimiento.')
        ->assertSee('Ana Ruiz')
        ->assertSee('Quitar');
})->with($looks);

it('shows what a new position still misses instead of saving it', function (): void {
    [$url, $center] = seatingCenter();

    visit("{$url}/seating/centers/{$center->id}/positions/create")
        ->type('name', 'Cajera')
        ->type('tasks', 'Cobra de pie.')
        ->click('Prolongada')
        ->type('continuous_minutes', '240')
        ->type('max_people_at_once', '2')
        ->press('Guardar puesto')
        ->assertSee('es obligatorio')
        ->assertNoJavaScriptErrors();
});
