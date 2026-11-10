<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Http\Controllers\PortalFeedbackController;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalFeedbackController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Sol']);
    $this->beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Paz']);
});

function feedbackLink(Employee $person): string
{
    return route('portal.feedback.show', (string) resolve(IssuePortalLink::class)->handle($person->id));
}

/** What the page says, without the token of the link, which differs by person, nor what every page shares. */
function feedbackProps(Illuminate\Testing\TestResponse $response): array
{
    return Illuminate\Support\Arr::only($response->viewData('page')['props'], ['company', 'survey', 'feedback']);
}

it('lives at /portal/{token}/feedback, throttled like the rest of the portal, with no way to ask for or redo a summary', function (): void {
    expect(route('portal.feedback.show', 'abc', absolute: false))->toBe('/portal/abc/feedback')
        ->and(Route::getRoutes()->getByName('portal.feedback.show')?->gatherMiddleware())->toContain('throttle:portal-views');

    $names = array_filter(array_map(fn (Illuminate\Routing\Route $route): ?string => $route->getName(), Route::getRoutes()->getRoutes()), fn (?string $name): bool => $name !== null && str_starts_with($name, 'portal.feedback'));
    expect(array_values($names))->toBe(['portal.feedback.show']);
});

it('shows the manager their group: each question against the company, strengths and opportunities, and the summary', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5], $this->beto->id => [6, 1]]);
    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $this->ana->id]);

    $this->get(feedbackLink($this->ana))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/portal-feedback')
            ->where('company', 'Acme')
            ->where('survey.name', $survey->name)
            ->has('feedback.items', 8)
            ->where('feedback.items.0.favorable', 100)
            ->where('feedback.items.0.vsCompany', 50)
            ->has('feedback.strengths', 2)
            ->has('feedback.opportunities', 2)
            ->where('feedback.line', false)
            ->where('feedback.summary.status', 'ready')
            ->where('feedback.summary.themes.valued.0.theme', 'Buen trato entre compañeros'));
});

it('puts no original comment, no id and no count of answers, invited or missing in the props', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    foreach (range(1, 12) as $i) {
        Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'item_key' => 'manager.keep', 'comment_group_id' => $this->ana->id, 'body' => "Escucha al equipo {$i}"]);
    }
    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $this->ana->id]);

    $this->get(feedbackLink($this->ana))->assertInertia(function (Assert $page): void {
        $props = $page->toArray()['props'];
        expectNoEngagementResponses($props);
        $json = json_encode($props, JSON_THROW_ON_ERROR);
        expect($json)->not->toContain('Escucha al equipo')
            ->not->toContain($this->ana->id)
            ->not->toContain($this->beto->id);
        foreach (['invited', 'responses', 'answered', 'missing', 'participation'] as $count) {
            expect($json)->not->toContain("\"{$count}\"");
        }
    });
});

it('gives the very same screen to a manager whose group is hidden, to one with no surveys and to someone with no team', function (): void {
    // Ana's group of four is under the minimum; Beto manages nobody; a survey exists for neither.
    climateManagerSurvey($this->tenant, [$this->ana->id => [4, 5]]);
    $noTeam = Employee::factory()->create(['tenant_id' => $this->tenant->id]);

    $hidden = feedbackProps($this->get(feedbackLink($this->ana)));
    $noGroup = feedbackProps($this->get(feedbackLink($this->beto)));
    $lonely = feedbackProps($this->get(feedbackLink($noTeam)));

    expect($hidden)->toBe($noGroup)->toBe($lonely)
        ->and($hidden['feedback'])->toBeNull()
        ->and($hidden['survey'])->toBeNull();
});

it('reads the group from the link and never from the request', function (): void {
    climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5], $this->beto->id => [6, 1]]);

    $this->get(feedbackLink($this->beto).'?manager='.$this->ana->id.'&survey=x')
        ->assertInertia(fn (Assert $page) => $page->where('feedback.items.0.favorable', 0)->where('feedback.items.0.vsCompany', -50));
});

it('does not open for a link that is not valid, and says nothing about why', function (): void {
    $this->get(route('portal.feedback.show', 'not-a-token'))->assertNotFound();
});

it('does not open for a manager who left', function (): void {
    climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    $link = feedbackLink($this->ana);
    $this->ana->update(['deactivated_at' => now()]);

    $this->get($link)->assertNotFound();
});

it('keeps showing the scores when the summary failed, the AI is off or it was withheld, and never any text', function (SummaryStatus $status): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    CommentSummary::factory()->status($status)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $this->ana->id]);
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'item_key' => 'manager.keep', 'comment_group_id' => $this->ana->id, 'body' => 'Texto original que nunca sale']);

    $this->get(feedbackLink($this->ana))->assertInertia(function (Assert $page): void {
        $page->has('feedback.items', 8)->where('feedback.summary', null);
        expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain('Texto original');
    });
})->with([SummaryStatus::Failed, SummaryStatus::AiOff, SummaryStatus::Withheld]);

it('says the summary is being prepared while it is pending', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    CommentSummary::factory()->status(SummaryStatus::Pending)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $this->ana->id]);

    $this->get(feedbackLink($this->ana))->assertInertia(fn (Assert $page) => $page->where('feedback.summary', ['status' => 'pending', 'themes' => null]));
});

it('shows the most recent closed survey', function (): void {
    $old = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 1]]);
    $old->update(['closed_at' => '2026-01-10 12:00:00', 'name' => 'Clima anterior']);
    $new = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);

    $this->get(feedbackLink($this->ana))->assertInertia(fn (Assert $page) => $page->where('survey.name', $new->name)->where('feedback.items.0.favorable', 100));
});

it('does not show a survey of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    climateManagerSurvey($other, [$this->ana->id => [6, 5]]);
    workIn($this->tenant);

    $this->get(feedbackLink($this->ana))->assertInertia(fn (Assert $page) => $page->where('feedback', null));
});
