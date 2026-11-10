<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Response;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Models\Tenant;

/** RH's panel with a closed climate survey: 36 answers, 20 in Ventas and 16 in Bodega, and 3 more in two tiny areas. */
function climateResultsPanel(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $sales = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ventas']);
    $warehouse = Area::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Bodega']);
    $survey = climateResultsSurvey($tenant, 20, fn (int $i): array => ['eng.pride' => 5, 'eng.motivation' => 4, 'eng.connection' => 4, 'eng.stay' => 4, 'eng.recommend' => 9, 'clarity.goals' => 4, 'clarity.contribution' => 5, 'clarity.priorities' => 4, 'recognition.specific' => 2, 'recognition.criteria' => 4], ['area_id' => $sales->id]);
    foreach (range(1, 16) as $ignored) {
        Response::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'area_id' => $warehouse->id, 'answers' => ['eng.pride' => 3, 'eng.motivation' => 3, 'eng.connection' => 3, 'eng.stay' => 3, 'eng.recommend' => 5, 'clarity.goals' => 3, 'clarity.contribution' => 3, 'clarity.priorities' => 3]]);
    }
    $survey->update(['invited' => 40, 'priorities' => [['driver' => 'recognition', 'level' => 'high']]]);

    return [$url, $survey->id];
}

it('shows RH the results of a closed survey and its cut by area', function (bool $dark, bool $mobile): void {
    [$url, $survey] = climateResultsPanel();

    visitPage('engagement/results', "{$url}/climate/{$survey}/results?cut=area", $dark, $mobile)
        ->assertSee('Resultados de clima')
        ->assertSee('Índice de compromiso')
        ->assertSee('36 de 40 contestaron')
        ->assertSee('Índice de recomendación')
        ->assertSee('Dónde actuar primero')
        ->assertSee('Reconocimiento')
        ->assertSee('Alta')
        ->assertSee('Qué mueve el compromiso')
        ->assertSee('Se reporta pregunta por pregunta.')
        ->assertSee('Fortalezas')
        ->assertSee('Oportunidades')
        ->assertSee('Por grupo')
        ->assertSee('Ventas')
        ->assertSee('Bodega')
        ->assertDontSee('Net Promoter');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('tells RH the results wait for the survey to close while it is open', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $survey = climateResultsSurvey($tenant, 6, closed: false);

    visitPage('engagement/results', "{$url}/climate/{$survey->id}/results")
        ->assertSee('Los resultados estarán disponibles al cerrar la encuesta')
        ->assertDontSee('Índice de compromiso');
});
