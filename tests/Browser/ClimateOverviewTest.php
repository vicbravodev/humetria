<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

it('invites RH to the first climate survey when there is none', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/climate", $dark, $mobile)
        ->assertSee('Todavía no hay una encuesta de clima')
        ->assertSee('Revisa las preguntas')
        ->assertSee('Elige a quién invitar')
        ->assertSee('Lanza y sigue el avance')
        ->assertSee('Preparar la primera encuesta')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('shows the survey in progress and the history of the closed ones', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    Survey::factory()->active()->create(['tenant_id' => $tenant->id, 'name' => 'Clima · noviembre 2026']);
    Survey::factory()->closed()->create(['tenant_id' => $tenant->id, 'kind' => App\Modules\Engagement\Enums\SurveyKind::Pulse, 'name' => 'Pulso anterior']);
    Survey::factory()->closed()->create(['tenant_id' => $tenant->id, 'name' => 'Clima · 2025']);

    visitPage('engagement/index', "{$url}/climate", $dark, $mobile)
        ->assertSee('Clima · noviembre 2026')
        ->assertSee('En curso')
        ->assertSee('Historial')
        ->assertSee('Clima · 2025')
        ->assertSee('Del clima anterior')
        ->assertSee('Todavía no hay compromisos')
        ->assertSee('Ver avance')
        ->assertDontSee('Pulso anterior')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('leads with the latest result and a new survey when nothing is in progress', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    climateResultsSurvey($tenant, 12);

    visitPage('engagement/index', "{$url}/climate")
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Escucha a tu gente')
        ->assertSee('Último resultado')
        ->assertSee('100 % favorable')
        ->assertSee('Ver resultados')
        ->assertSee('Nueva encuesta')
        ->assertNoJavaScriptErrors();
});

it('starts a draft and opens it', function (): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/climate")
        ->click('Preparar la primera encuesta')
        ->assertSee('Borrador')
        ->assertNoJavaScriptErrors();
});
