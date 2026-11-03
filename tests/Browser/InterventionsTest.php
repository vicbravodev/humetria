<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Tenancy\Models\Tenant;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

/** @return array{string, string} The panel's address and the id of a closed evaluation of the company the user signed in to. */
function programEvaluation(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);

    return [$url, closedEvaluationWith($tenant, nom035Answers('nom035.guide-ii', 'always'))->id];
}

it('offers to create the Programa of an evaluation that asks for one', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program", $dark, $mobile)
        ->assertSee('Programa de intervención')
        ->assertSee('Esta evaluación pide un Programa de intervención')
        ->assertSee('Crear el Programa');
})->with($looks);

it('creates the Programa from the page and shows its rows', function (): void {
    [$url, $campaign] = programEvaluation();

    visit("{$url}/campaigns/{$campaign}/program")
        ->press('Crear el Programa')
        ->assertSee('Programa creado.')
        ->assertSee('Calificación final')
        ->assertSee('Muy alto');
});

it('shows the rows of a Programa that already exists', function (bool $dark, bool $mobile): void {
    [$url, $campaign] = programEvaluation();
    resolve(CreateProgram::class)->handle($campaign, null);

    visitPage('interventions/show', "{$url}/campaigns/{$campaign}/program", $dark, $mobile)
        ->assertSee('Calificación final')
        ->assertSee('Nivel más alto');
})->with($looks);
