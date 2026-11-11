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
        ->assertSee('Crear la primera encuesta de clima')
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
        ->assertDontSee('Pulso anterior')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('starts a draft and opens it', function (): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/climate")
        ->click('Crear la primera encuesta de clima')
        ->assertSee('Borrador')
        ->assertNoJavaScriptErrors();
});
