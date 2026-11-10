<?php

declare(strict_types=1);

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
