<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Models\Survey;
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
