<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Models\Tenant;

/**
 * The panel of Autopartes Demo (acme) with a closed climate survey: twelve comments about the company, the summary of
 * them (or a failed one when `$failed`) and a notice of mentions; returns the address of its comments.
 */
function climateCommentsPanel(bool $failed = false): string
{
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    $survey = Survey::factory()->closed()->create(['tenant_id' => $tenant->id, 'sensitive_mentions' => true]);
    $summary = CommentSummary::factory();
    $summary->when($failed, fn ($factory) => $factory->status(SummaryStatus::Failed))->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id]);
    foreach (range(1, 12) as $i) {
        Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'item_key' => 'open.best', 'body' => "Me gusta el ambiente {$i}"]);
    }

    return "{$url}/climate/{$survey->id}/comments";
}

it('shows RH the summary, the originals and the notice of mentions', function (bool $dark, bool $mobile): void {
    visitPage('engagement/comments', climateCommentsPanel(), $dark, $mobile)
        ->assertSee('Comentarios sobre la empresa')
        ->assertSee('Hubo menciones de posible acoso o violencia.')
        ->assertSee('Resumen de los comentarios')
        ->assertSee('Buen trato entre compañeros')
        ->assertSee('Lo mejor de trabajar aquí')
        ->assertSee('Me gusta el ambiente')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('offers to retry a summary that failed', function (): void {
    visitPage('engagement/comments', climateCommentsPanel(true))
        ->assertSee('No se pudo hacer el resumen de los comentarios.')
        ->assertSee('Reintentar el resumen')
        ->assertNoJavaScriptErrors();
});
