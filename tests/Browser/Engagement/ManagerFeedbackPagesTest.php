<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;

/**
 * The panel of Autopartes Demo (acme) with a closed climate survey where Marta Ruiz's group answered, with ten comments about
 * her and a summary; returns the address of the survey and Marta's id.
 *
 * @return array{string, string}
 */
function climateManagerPanel(): array
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $marta = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Marta Ruiz']);
    Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Luis Mena']);
    $survey = climateManagerSurvey($tenant, [$marta->id => [6, 4]]);
    CommentSummary::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $marta->id]);
    foreach (range(1, 10) as $i) {
        Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'item_key' => 'manager.keep', 'comment_group_id' => $marta->id, 'body' => "Explica bien las prioridades {$i}"]);
    }

    return ["{$url}/climate/{$survey->id}", $marta->id];
}

it('lists the managers whose group is shown, by name', function (bool $dark, bool $mobile): void {
    [$survey] = climateManagerPanel();

    visitPage('engagement/managers', "{$survey}/managers", $dark, $mobile)
        ->assertSee('Retroalimentación por jefe')
        ->assertSee('Marta Ruiz')
        ->assertDontSee('Luis Mena')
        ->assertSee('Ver retroalimentación')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('shows a manager the feedback of the group, the summary and the comments to who manages climate', function (bool $dark, bool $mobile): void {
    [$survey, $marta] = climateManagerPanel();

    visitPage('engagement/manager', "{$survey}/managers/{$marta}", $dark, $mobile)
        ->assertSee('Marta Ruiz')
        ->assertSee('Pregunta por pregunta')
        ->assertSee('Mi jefe me aclara las prioridades cuando tengo varias tareas pendientes.')
        ->assertSee('Fortalezas')
        ->assertSee('Resumen de los comentarios')
        ->assertSee('Buen trato entre compañeros')
        ->assertSee('Comentarios del grupo')
        ->assertSee('Explica bien las prioridades')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);
