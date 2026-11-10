<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    $this->beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Soto']);
});

function managersUrl(Tenant $tenant, Survey $survey): string
{
    return route('tenant.climate.managers', [$tenant, $survey]);
}

function managerUrl(Tenant $tenant, Survey $survey, string $manager): string
{
    return route('tenant.climate.managers.show', [$tenant, $survey, $manager]);
}

it('lists the managers whose group is shown, by name and never as a ranking', function (): void {
    // Beto has the best score and Ana the worst: the list is still by name.
    $survey = climateManagerSurvey($this->tenant, [$this->beto->id => [6, 5], $this->ana->id => [6, 1]]);

    $this->actingAs($this->hr)->get(managersUrl($this->tenant, $survey))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/managers')
            ->where('available', true)
            ->where('survey.id', $survey->id)
            ->where('managers.total', 2)
            ->where('managers.data', [['id' => $this->ana->id, 'name' => 'Ana Ruiz'], ['id' => $this->beto->id, 'name' => 'Beto Soto']])
            ->where('hasHidden', false));
});

it('shows a group of exactly five and hides one of four, in the list and by its URL, saying only that something is hidden', function (): void {
    $small = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carla Paz']);
    $five = climateManagerSurvey($this->tenant, [$this->ana->id => [5, 5]]);
    $four = climateManagerSurvey($this->tenant, [$small->id => [4, 5]]);

    $this->actingAs($this->hr);
    $this->get(managersUrl($this->tenant, $five))->assertInertia(fn (Assert $page) => $page
        ->where('managers.data', [['id' => $this->ana->id, 'name' => 'Ana Ruiz']])
        ->where('hasHidden', false));
    $this->get(managerUrl($this->tenant, $five, $this->ana->id))->assertOk();
    $this->get(managersUrl($this->tenant, $four))->assertInertia(fn (Assert $page) => $page
        ->where('managers.total', 0)
        ->where('hasHidden', true));
    $this->get(managerUrl($this->tenant, $four, $small->id))->assertNotFound();
});

it('hides, in the list and by its URL, the group the complementary suppression hid', function (): void {
    $small = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carla Paz']);
    // 6 shown, 5 would be shown but the 4 hidden ones plus it must not be told apart: it goes too.
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5], $this->beto->id => [5, 5], $small->id => [4, 5]]);

    $this->actingAs($this->hr);
    $this->get(managersUrl($this->tenant, $survey))->assertInertia(fn (Assert $page) => $page->where('managers.total', 1));
    $this->get(managerUrl($this->tenant, $survey, $this->beto->id))->assertNotFound();
});

it('names the feedback of a manager question by question, without how many were invited or answered', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5], $this->beto->id => [6, 1]]);

    $this->actingAs($this->hr)->get(managerUrl($this->tenant, $survey, $this->ana->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/manager')
            ->where('manager', ['id' => $this->ana->id, 'name' => 'Ana Ruiz'])
            ->has('feedback.items', 8)
            ->where('feedback.items.0.favorable', 100)
            ->where('feedback.items.0.vsCompany', 50)
            ->has('feedback.strengths', 2)
            ->has('feedback.opportunities', 2)
            ->where('feedback.line', false)
            ->where('feedback.summary', null)
            ->missing('feedback.responses')
            ->missing('feedback.invited')
            ->missing('feedback.answered'));
});

it('brings the AI summary of the group of comments of the manager', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => $this->ana->id]);

    $this->actingAs($this->hr)->get(managerUrl($this->tenant, $survey, $this->ana->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('feedback.summary.status', 'ready')
            ->where('feedback.summary.themes.valued.0.theme', 'Buen trato entre compañeros')
            ->where('feedback.summary.themes.valued.0.count', 4));
});

/** Comments of the group of Ana, `$count` of them. */
function managerGroupComments(Tenant $tenant, Survey $survey, string $manager, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        Comment::factory()->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'item_key' => $i % 2 === 0 ? 'manager.keep' : 'manager.change', 'comment_group_id' => $manager, 'body' => "Escucha mucho {$i}"]);
    }
}

it('gives the original comments of the group to who manages climate, from ten and only the text', function (int $count, string $state): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    managerGroupComments($this->tenant, $survey, $this->ana->id, $count);
    // The comments of another group never come in.
    managerGroupComments($this->tenant, $survey, $this->beto->id, 12);

    $this->actingAs($this->hr)->get(managerUrl($this->tenant, $survey, $this->ana->id))
        ->assertInertia(function (Assert $page) use ($count, $state): void {
            $props = $page->toArray()['props'];
            expect($props['commentsState'])->toBe($state);

            if ($state === 'shown') {
                expect($props['comments']['total'])->toBe($count)
                    ->and($props['comments']['data'])->toHaveCount(10)
                    ->and(array_keys($props['comments']['data'][0]))->toBe(['body']);
            } else {
                expect($props['comments'])->toBeNull();
            }
        });
})->with([[9, 'few'], [10, 'shown'], [0, 'few']]);

it('gives the viewer the summary only, never a comment', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    managerGroupComments($this->tenant, $survey, $this->ana->id, 12);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(managerUrl($this->tenant, $survey, $this->ana->id))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->where('commentsState', 'hidden')->where('comments', null);
            expectNoEngagementResponses($page->toArray()['props']);
        });
});

it('orders the comments by their random id, not by arrival', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    managerGroupComments($this->tenant, $survey, $this->ana->id, 12);

    $this->actingAs($this->hr)->get(managerUrl($this->tenant, $survey, $this->ana->id))
        ->assertInertia(function (Assert $page): void {
            $bodies = collect($page->toArray()['props']['comments']['data'])->pluck('body')->all();
            $expected = Comment::query()->where('comment_group_id', $this->ana->id)->orderBy('id')->limit(10)->pluck('body')->all();
            expect($bodies)->toBe($expected);
        });
});

it('puts no individual response in the props of the list or of a group without originals', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'body' => 'Mi jefe grita en la bodega']);

    $this->actingAs($this->hr);
    $this->get(managersUrl($this->tenant, $survey))->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
    $this->get(managerUrl($this->tenant, $survey, $this->ana->id))->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
});

it('says the results come when the survey closes', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    $survey->update(['status' => 'active', 'closed_at' => null]);

    $this->actingAs($this->hr);
    $this->get(managersUrl($this->tenant, $survey))->assertInertia(fn (Assert $page) => $page->where('available', false)->where('managers.total', 0));
    $this->get(managerUrl($this->tenant, $survey, $this->ana->id))->assertNotFound();
    expect(AuditEntry::query()->where('event', 'engagement.manager_results_viewed')->count())->toBe(0);
});

it('does not open for another company, a pulse survey, a role that does not view climate or a stranger', function (): void {
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = climateManagerSurvey($other, [$this->ana->id => [6, 5]]);
    workIn($this->tenant);
    $pulse = Survey::factory()->closed()->pulse()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr);
    $this->get(managersUrl($this->tenant, $foreign))->assertNotFound();
    $this->get(managerUrl($this->tenant, $foreign, $this->ana->id))->assertNotFound();
    $this->get(managersUrl($this->tenant, $pulse))->assertNotFound();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(managersUrl($this->tenant, $survey))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(managerUrl($this->tenant, $survey, $this->ana->id))->assertNotFound();
    auth()->logout();
    $this->get(managerUrl($this->tenant, $survey, $this->ana->id))->assertRedirect();
});

it('writes a log and an audit entry that say which survey and which manager and carry no answer', function (): void {
    Log::spy();
    $survey = climateManagerSurvey($this->tenant, [$this->ana->id => [6, 5]]);

    $this->actingAs($this->hr)->get(managerUrl($this->tenant, $survey, $this->ana->id))->assertOk();

    $entry = AuditEntry::query()->where('event', 'engagement.manager_results_viewed')->sole();
    expect($entry->subject_id)->toBe($survey->id)->and($entry->metadata)->toBe(['manager_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->with('engagement.manager_results_viewed', ['survey_id' => $survey->id, 'manager_id' => $this->ana->id])->once();
});

it('reads a bounded number of queries however many managers there are', function (): void {
    $groups = [];
    foreach (range(1, 12) as $i) {
        $groups[Employee::factory()->create(['tenant_id' => $this->tenant->id])->id] = [5, 4];
    }
    $survey = climateManagerSurvey($this->tenant, $groups);

    $this->actingAs($this->hr);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->get(managersUrl($this->tenant, $survey))->assertOk();

    expect($queries)->toBeLessThan(25);
});
