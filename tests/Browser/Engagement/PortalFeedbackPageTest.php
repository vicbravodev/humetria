<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;

/**
 * The portal of Marta Ruiz, who manages a group of six at Autopartes del Bajío that answered a closed climate survey; with a
 * summary of its comments when `$summary`, nothing at all when `$answered` is false.
 */
function managerPortalFeedback(bool $answered = true): string
{
    $tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    workIn($tenant);
    $marta = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Marta Ruiz']);

    if ($answered) {
        $survey = climateManagerSurvey($tenant, [$marta->id => [6, 4]]);
        CommentSummary::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $marta->id]);
    }

    return route('portal.feedback.show', (string) resolve(IssuePortalLink::class)->handle($marta->id));
}

it('shows the manager the feedback of their group in their portal', function (bool $dark, bool $mobile): void {
    visitPage('engagement/portal-feedback', managerPortalFeedback(), $dark, $mobile)
        ->assertSee('Autopartes del Bajío')
        ->assertSee('Lo que dijo su equipo')
        ->assertSee('Solo se muestran resultados de grupo')
        ->assertSee('Mi jefe me aclara las prioridades cuando tengo varias tareas pendientes.')
        ->assertSee('Fortalezas')
        ->assertSee('Buen trato entre compañeros')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('says there is nothing to show yet, without saying why', function (bool $dark, bool $mobile): void {
    visitPage('engagement/portal-feedback', managerPortalFeedback(false), $dark, $mobile)
        ->assertSee('Todavía no hay resultados para mostrar')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with([
    'light desktop' => [false, false],
    'dark mobile' => [true, true],
]);
