<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Commitment;
use App\Modules\Tenancy\Models\Tenant;

/** RH's panel with a closed climate survey, its priorities and two commitments for the company. */
function commitmentsPanel(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $survey = climateResultsSurvey($tenant, 12);
    $survey->update(['priorities' => [['driver' => 'recognition', 'level' => 'high']]]);
    Commitment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'heard' => 'Que el buen trabajo no se reconoce.', 'will_do' => 'Reconocer por escrito cada logro del mes.', 'owner_name' => 'Ana Torres']);
    Commitment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'status' => 'done', 'will_do' => 'Publicar los criterios de reconocimiento.']);

    return [$url, $survey->id];
}

it('shows RH the commitments of a closed survey and the ideas for the themes to act on', function (bool $dark, bool $mobile): void {
    [$url, $survey] = commitmentsPanel();

    visitPage('engagement/commitments', "{$url}/climate/{$survey}/commitments", $dark, $mobile)
        ->assertSee('Nos dijeron / Haremos')
        ->assertSee('Nuevo compromiso')
        ->assertSee('Sin publicar')
        ->assertSee('Publicar en el portal')
        ->assertSee('Que el buen trabajo no se reconoce.')
        ->assertSee('Reconocer por escrito cada logro del mes.')
        ->assertSee('Ana Torres')
        ->assertSee('Toda la empresa')
        ->assertSee('Cumplido')
        ->assertSee('Ideas para empezar')
        ->assertSee('Reconocimiento')
        ->assertSee('2 compromisos · un grupo tiene a lo más 3')
        ->assertSee('Marcar en marcha')
        ->assertDontSee('Descartar');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('opens the dialog to write a commitment', function (): void {
    [$url, $survey] = commitmentsPanel();

    visitPage('engagement/commitments', "{$url}/climate/{$survey}/commitments")
        ->click('Nuevo compromiso')
        ->assertSee('Para quién es')
        ->assertSee('Nos dijeron')
        ->assertSee('Haremos')
        ->assertSee('Responsable');
});

it('tells RH the commitments wait for the survey to close while it is open', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $survey = climateResultsSurvey($tenant, 6, closed: false);

    visitPage('engagement/commitments', "{$url}/climate/{$survey->id}/commitments")
        ->assertSee('Los compromisos se registran al cerrar la encuesta')
        ->assertDontSee('Nuevo compromiso');
});

it('moves a commitment to its next state with the one button of its card, and the rest from its menu', function (): void {
    [$url, $survey] = commitmentsPanel();

    visitPage('engagement/commitments', "{$url}/climate/{$survey}/commitments")
        ->click('Marcar en marcha')
        ->assertSee('Marcar cumplido')
        ->click('button[aria-label="Más acciones de «Reconocer por escrito cada logro del mes.»"]')
        ->assertSee('Reabrir')
        ->assertSee('Descartar')
        ->assertNoJavaScriptErrors();
});
