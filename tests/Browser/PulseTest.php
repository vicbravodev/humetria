<?php

declare(strict_types=1);

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

// Light and dark, on a desktop and on a phone.
$pulseLooks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/** The pulse draft of the company of the signed-in user, with six people (two of them without an email). */
function pulseDraftWithPeople(PulseMode $mode = PulseMode::Tour): Survey
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $plant = WorkCenter::factory()->for($tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $tenant->id]);
    Employee::factory()->count(4)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id, 'email' => fn () => fake()->unique()->safeEmail()]);
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id, 'email' => null]);

    return Survey::factory()->pulse()->create(['tenant_id' => $tenant->id, 'pulse_mode' => $mode, 'name' => 'Pulso · diciembre 2026']);
}

it('opens the pulse on the next step: choose what to look at', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/pulse", $dark, $mobile)
        ->assertSee('Todavía no hay un pulso')
        ->assertSee('Recorrido')
        ->assertSee('Crear el primer pulso')
        ->assertSee('Todavía no está programado')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

it('starts a draft in the chosen mode and shows it', function (): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/pulse")
        ->click('Jefe')
        ->click('Crear el primer pulso')
        ->assertSee('Pulso ·')
        ->assertSee('Jefe')
        ->assertSee('Su jefe directo')
        ->assertNoJavaScriptErrors();
});

it('schedules the pulse from its home and says when the next one goes out', function (): void {
    $url = signInToPanel();

    visitPage('engagement/index', "{$url}/pulse")
        ->click('Programar')
        ->assertSee('Programar el pulso')
        ->assertScript(cspViolations(), '[]')
        ->click('Guardar')
        ->assertSee('Sale el')
        ->assertSee('de cada mes')
        ->assertSee('Pausar')
        ->click('Pausar')
        ->assertSee('En pausa')
        ->assertNoJavaScriptErrors();
});

it('opens on the next pulse and how the engagement moves, never on an empty pulse, once there is history', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    pulseResultsSurvey($tenant, PulseMode::Tour, 12, 4);
    App\Modules\Engagement\Models\PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => now()->addDays(4)->toDateString()]);

    visitPage('engagement/index', "{$url}/pulse", $dark, $mobile)
        ->assertDontSee('Todavía no hay un pulso')
        ->assertSee('Siguiente pulso')
        ->assertSee('Sale en 4 días')
        ->assertSee('Cómo vamos')
        ->assertSee('Ver tendencia')
        ->assertSee('Crear un pulso ahora')
        ->assertSee('Historial')
        ->assertSee('Recorrido')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

it('creates a pulse now from a dialog while one is scheduled', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    App\Modules\Engagement\Models\PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => now()->addDays(4)->toDateString()]);

    visitPage('engagement/index', "{$url}/pulse")
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Escucha a tu gente')
        ->click('Crear un pulso ahora')
        ->assertSee('¿Qué quieres mirar en este pulso?')
        ->click('Crear borrador')
        ->assertSee('Pulso ·')
        ->assertNoJavaScriptErrors();
});

it('lets RH review the draft of a pulse before launching it', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = pulseDraftWithPeople();

    visitPage('engagement/show', "{$url}/pulse/{$survey->id}", $dark, $mobile)
        ->assertSee('Pulso · diciembre 2026')
        ->assertSee('Recorrido')
        ->assertSee('Lanzar e invitar a 4 personas')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

it('shows how the company moves from survey to survey', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    $first = climateResultsSurvey($tenant, 40);
    $first->update(['closed_at' => now()->subMonths(2)]);
    $second = climateResultsSurvey($tenant, 40, fn (int $i): array => collect(ItemBank::closed())->mapWithKeys(fn (BankItem $item): array => [$item->key => $item->scale === ItemScale::Recommend ? ($i % 2 === 0 ? 10 : 3) : ($i % 2 === 0 ? 4 : 2)])->all());
    $second->update(['closed_at' => now()->subMonth()]);

    visitPage('engagement/trend', "{$url}/pulse/trend", $dark, $mobile)
        ->assertSee('Tendencia')
        ->assertSee('2 mediciones')
        ->assertSee('El compromiso bajó')
        ->assertSee('Índice de compromiso')
        ->assertSee('Línea base')
        ->assertSee('Índice de recomendación')
        ->assertSee('Por tema')
        ->assertSee('Claridad y alineación')
        ->assertSee('Bajó')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

it('says when the next pulse goes out while there is a single wave', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    climateResultsSurvey($tenant, 40);
    App\Modules\Engagement\Models\PulseSchedule::factory()->create(['tenant_id' => $tenant->id, 'next_run_on' => now()->addDays(4)->toDateString()]);

    visitPage('engagement/trend', "{$url}/pulse/trend")
        ->assertSeeIn('nav[aria-label="Ruta"]', 'Escucha a tu gente')
        ->assertSee('Con el siguiente pulso verás aquí la tendencia')
        ->assertSee('El siguiente sale el')
        ->assertSee('Ver el pulso programado')
        ->assertNoJavaScriptErrors();
});

it('says there is nothing to compare yet, with the next step', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('engagement/trend', "{$url}/pulse/trend", $dark, $mobile)
        ->assertSee('Todavía no hay con qué comparar')
        ->assertSee('Programa tu primer pulso')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

/** A closed pulse of the company of the signed-in user, tour mode, answered by twelve people. */
function closedPulseWithAnswers(): Survey
{
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $refs = App\Modules\Engagement\Support\Bank\PulseComposer::compose(PulseMode::Tour, [], 0, true);
    $survey = Survey::factory()->closed()->pulse()->create(['tenant_id' => $tenant->id, 'items' => $refs, 'invited' => 15]);
    $answers = collect($refs)->mapWithKeys(fn (string $ref): array => [explode('@', $ref)[0] => 4])->all();
    App\Modules\Engagement\Models\Response::factory()->count(12)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'answers' => $answers]);

    return $survey;
}

it('shows RH the results of a pulse with the improvements seen and only the themes it asked about', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = closedPulseWithAnswers();

    visitPage('engagement/results', "{$url}/pulse/{$survey->id}/results", $dark, $mobile)
        ->assertSee('Resultados de pulso')
        ->assertSee('Mejoras vistas')
        ->assertSee('Claridad y alineación')
        ->assertDontSee('Compensación y prestaciones')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);

it('shows RH the commitments of a pulse with the improvements seen', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    $survey = closedPulseWithAnswers();

    visitPage('engagement/commitments', "{$url}/pulse/{$survey->id}/commitments", $dark, $mobile)
        ->assertSee('Mejoras vistas')
        ->assertSee('Nuevo compromiso')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($pulseLooks);
