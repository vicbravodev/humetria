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
        ->assertSee('Grupos de 5 o más')
        ->assertSee('36 de 40 contestaron')
        ->assertSee('Escribir compromisos')
        ->assertSee('Resumen')
        ->assertSee('Donde más puedes mover la aguja es Reconocimiento.')
        ->assertSee('Índice de compromiso')
        ->assertSee('respuestas favorables')
        ->assertSee('Índice de recomendación')
        ->assertSee('en una escala de −100 a 100')
        ->assertSee('Dónde actuar primero')
        ->assertSee('Prioridad alta')
        ->assertSee('Prioridad es asociación con el compromiso, no causa.')
        ->assertSee('Fortalezas')
        ->assertSee('Oportunidades')
        ->assertSee('Por grupo')
        ->assertSee('Toda la empresa')
        ->assertSee('Ventas')
        ->assertSee('Bodega')
        ->assertSee('Protegido')
        ->assertDontSee('Net Promoter');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('shows RH every question with its share and its five-step bar', function (bool $dark, bool $mobile): void {
    [$url, $survey] = climateResultsPanel();

    visitPage('engagement/items', "{$url}/climate/{$survey}/results/items", $dark, $mobile)
        ->assertSee('Resultados por pregunta')
        ->assertSee('Compromiso')
        ->assertSee('Claridad y alineación')
        ->assertSee('Conozco las principales metas que busca alcanzar esta empresa.')
        ->assertSee('36 respuestas')
        ->assertSee('Se reporta pregunta por pregunta.')
        ->assertSee('Menos de 5 personas la contestaron.');
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
        ->assertSee('Los resultados se abren al cerrar la encuesta')
        ->assertSee('Ver avance')
        ->assertDontSee('Índice de compromiso');
});

it('shows no result of a survey that closed with fewer than five answers, nor how many are missing', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $survey = climateResultsSurvey($tenant, 3);

    visitPage('engagement/results', "{$url}/climate/{$survey->id}/results")
        ->assertSee('Todavía no hay suficientes respuestas para mostrar resultados')
        ->assertSee('Se muestran a partir de 5 respuestas')
        ->assertDontSee('Índice de compromiso')
        ->assertDontSee('respuestas favorables')
        ->assertDontSee('faltan');
});
