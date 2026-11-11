<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Bus;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * The draft of the company of the signed-in user, with six people (two of them without an email) in its only work center.
 */
function climateDraftWithPeople(): Survey
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $plant = WorkCenter::factory()->for($tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->count(4)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id, 'email' => fn () => fake()->unique()->safeEmail()]);
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id, 'email' => null]);

    return Survey::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Clima · noviembre 2026']);
}

it('lets RH review the draft before launching it', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = climateDraftWithPeople();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}", $dark, $mobile)
        ->assertSee('Clima · noviembre 2026')
        ->assertSee('Borrador')
        ->assertSee('Revisa tres cosas y lanza.')
        ->assertSee('2 personas no tienen correo.')
        ->assertSee('Lo que van a contestar')
        ->assertSee('Lanzar e invitar a 4 personas')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('shows the open commitments of the last survey before the launch form', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = climateDraftWithPeople();
    $previous = Survey::factory()->closed()->create(['tenant_id' => $survey->tenant_id, 'closed_at' => now()->subMonth()]);
    Commitment::factory()->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $previous->id, 'will_do' => 'Presentar las metas por área.']);

    visitPage('engagement/show', "{$url}/climate/{$survey->id}", $dark, $mobile)
        ->assertSee('Compromisos abiertos de la encuesta anterior')
        ->assertSee('Presentar las metas por área.')
        ->assertSee('Lanzar e invitar a 4 personas')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('opens a section of the questionnaire to read its questions', function (): void {
    $url = signInToPanel();
    $survey = climateDraftWithPeople();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}")
        ->click('summary:has-text("Compromiso")')
        ->assertSee('Me siento orgulloso')
        ->assertNoJavaScriptErrors();
});

it('launches the draft and shows it in progress', function (): void {
    Bus::fake();
    $url = signInToPanel();
    $survey = climateDraftWithPeople();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}")
        ->click('button:has-text("Lanzar e invitar")')
        ->assertSee('En curso')
        ->assertDontSee('Revisa tres cosas y lanza.')
        ->assertNoJavaScriptErrors();
});

/**
 * A survey in progress of the signed-in user's company: eight invited to a plant and six to the office, four of them answered.
 */
function climateActiveSurvey(string $state = 'active'): Survey
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    $office = WorkCenter::factory()->for($tenant)->create(['name' => 'Oficina Sur']);
    $survey = Survey::factory()->{$state}()->create(['tenant_id' => $tenant->id, 'name' => 'Clima · noviembre 2026', 'invited' => 14]);
    Invitation::factory()->count(4)->create(['survey_id' => $survey->id, 'tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'answered' => true]);
    Invitation::factory()->count(4)->create(['survey_id' => $survey->id, 'tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    Invitation::factory()->count(6)->create(['survey_id' => $survey->id, 'tenant_id' => $tenant->id, 'work_center_id' => $office->id]);

    return $survey;
}

it('follows the survey in progress by counts, with the groups under the minimum left out', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = climateActiveSurvey();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}", $dark, $mobile)
        ->assertSee('En curso')
        ->assertSee('de 14 contestaron')
        ->assertSee('Faltan 10 por contestar.')
        ->assertSee('Participación por grupo')
        ->assertSee('Planta Norte')
        ->assertSee('Oficina Sur')
        ->assertSee('Recordar a 10')
        ->assertSee('Cerrar encuesta')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('changes the cut of the participation', function (): void {
    $url = signInToPanel();
    $survey = climateActiveSurvey();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}")
        ->click('button:has-text("Por área")')
        ->assertSee('Ningún grupo de este tipo llega a 5 personas.')
        ->assertNoJavaScriptErrors();
});

it('closes the survey after asking', function (): void {
    $url = signInToPanel();
    $survey = climateActiveSurvey();

    visitPage('engagement/show', "{$url}/climate/{$survey->id}")
        ->click('button:has-text("Cerrar encuesta")')
        ->assertSee('¿Cerrar la encuesta?')
        ->click('[role=dialog] button:has-text("Cerrar encuesta")')
        ->assertSee('Cerrada')
        ->assertDontSee('Recordar a')
        ->assertNoJavaScriptErrors();
});

it('shows how a closed survey ended', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = climateActiveSurvey('closed');

    visitPage('engagement/show', "{$url}/climate/{$survey->id}", $dark, $mobile)
        ->assertSee('Cerrada')
        ->assertSee('de 14 contestaron')
        ->assertDontSee('Faltan')
        ->assertDontSee('Cerrar encuesta')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);
