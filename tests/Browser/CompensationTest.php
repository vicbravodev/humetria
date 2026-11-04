<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows Compensación of a company with its next step', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/index', "{$url}/compensation", $dark, $mobile)
        ->assertSee('Compensación')
        ->assertSee('Cargar el padrón');
})->with($looks);

it('shows Compensación as the start of a union', function (bool $dark, bool $mobile): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(ownerOf($union));

    visitPage('compensation/index', '/admin/sindicato', $dark, $mobile)
        ->assertSee('Compensación')
        ->assertSee('Cargar el padrón');
})->with($looks);

it('shows the catalog of benefits with the base ones and the risk premium', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('compensation/benefits', "{$url}/compensation/benefits", $dark, $mobile)
        ->assertSee('Prestaciones')
        ->assertSee('Aguinaldo')
        ->assertSee('Prima de riesgo de trabajo');
})->with($looks);
