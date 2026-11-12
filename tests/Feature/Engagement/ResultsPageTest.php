<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\SurveyResults;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SurveyResults::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

function climateResultsUrl(Tenant $tenant, Survey $survey, string $query = ''): string
{
    return route('tenant.climate.results', [$tenant, $survey]).$query;
}

it('shows the company results of a closed survey to every role that views climate', function (TenantRole $role): void {
    $survey = climateResultsSurvey($this->tenant, 6);

    $this->actingAs(memberOf($this->tenant, $role))->get(climateResultsUrl($this->tenant, $survey))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/results')
            ->where('available', true)
            ->where('survey.id', $survey->id)
            ->where('participation', ['invited' => 12, 'responses' => 6, 'percent' => 50])
            ->where('index', 100)
            ->where('stay.favorable', 100)
            ->where('recommendation.shown', true)
            ->where('recommendation.index', null)
            ->where('recommendation.bands.promoters', 100)
            ->where('cut', null)
            ->where('groups', null)
            ->has('drivers', 10)
            ->where('drivers.0.key', 'clarity')
            ->where('drivers.0.favorable', 100)
            ->where('drivers.3.descriptive', true)
            ->where('drivers.3.favorable', null)
            ->has('highlights.strengths', 3)
            ->has('highlights.opportunities', 3)
            ->where('comparison', null)
            ->has('cuts', 4));
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer]);

it('does not open for a role that does not view climate, nor for someone outside the company', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);

    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(climateResultsUrl($this->tenant, $survey))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(climateResultsUrl($this->tenant, $survey))->assertNotFound();
    auth()->logout();
    $this->get(climateResultsUrl($this->tenant, $survey))->assertRedirect();
});

it('does not find the survey of another company, nor a pulse survey, nor one that does not exist', function (): void {
    $other = Tenant::factory()->create();
    $survey = climateResultsSurvey($other, 6);
    workIn($this->tenant);
    $pulse = Survey::factory()->closed()->pulse()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr);
    $this->get(climateResultsUrl($this->tenant, $survey))->assertNotFound();
    $this->get(climateResultsUrl($this->tenant, $pulse))->assertNotFound();
    $this->get(route('tenant.climate.results', [$this->tenant, '3bdc0e57-8a35-4e0c-83a8-3a4f4c0c8a10']))->assertNotFound();
});

it('says the results come when the survey closes and calculates nothing while it is open', function (): void {
    $survey = climateResultsSurvey($this->tenant, 7, closed: false);
    $reads = 0;
    DB::listen(function ($query) use (&$reads): void {
        $reads += str_contains($query->sql, 'engagement_responses') ? 1 : 0;
    });

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/results')
            ->where('available', false)
            ->where('participation', null)
            ->where('index', null)
            ->where('drivers', [])
            ->where('groups', null));

    expect($reads)->toBe(0);
    expect(AuditEntry::query()->where('event', 'engagement.results_viewed')->count())->toBe(0);
});

it('cuts by one attribute at a time and hides the groups under the minimum without saying how many', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $big = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $survey = climateResultsSurvey($this->tenant, 8, segment: ['work_center_id' => $plant->id, 'area_id' => $big->id]);
    // Two areas of three answers each: alone they are too small, so they only count for the center, together.
    foreach (range(1, 2) as $ignored) {
        $small = Area::factory()->create(['tenant_id' => $this->tenant->id]);
        foreach (range(1, 3) as $ignoredToo) {
            Response::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'work_center_id' => $plant->id, 'area_id' => $small->id, 'answers' => ['clarity.goals' => 1]]);
        }
    }

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cut', 'area')
            ->has('groups', 1)
            ->where('groups.0.name', 'Ventas')
            ->where('groups.0.responses', 8)
            ->where('hasHidden', true)
            ->has('groups.0.drivers', 10)
            ->has('groups.0.strengths', 3)
            ->has('groups.0.opportunities', 3));

    $this->get(climateResultsUrl($this->tenant, $survey, '?cut=work_center'))
        ->assertInertia(fn (Assert $page) => $page->where('groups.0.name', 'Planta Norte')->where('groups.0.responses', 14));
});

it('accepts one cut and nothing else', function (string $query): void {
    $survey = climateResultsSurvey($this->tenant, 6);

    $this->actingAs($this->hr)->getJson(climateResultsUrl($this->tenant, $survey, $query))->assertUnprocessable();
})->with(['two cuts' => '?cut[]=area&cut[]=tenure', 'unknown' => '?cut=salary', 'empty array' => '?cut[]=']);

it('gives the recommendation index and the arrows only from thirty responses', function (int $count, bool $trend): void {
    $survey = climateResultsSurvey($this->tenant, $count, fn (int $i): array => ['eng.recommend' => $i % 2 === 0 ? 10 : 3, 'eng.pride' => 4, 'eng.motivation' => 4, 'eng.connection' => 4]);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendation.index', $trend ? 0 : null)
            ->has('recommendation.bands'));
})->with([[29, false], [30, true]]);

it('compares with the previous closed survey of the company, with an arrow only when both have thirty responses', function (): void {
    $before = climateResultsSurvey($this->tenant, 30, fn (int $i): array => ['eng.pride' => $i < 15 ? 4 : 2, 'eng.motivation' => 4, 'eng.connection' => 4, 'clarity.goals' => 4, 'clarity.contribution' => 4, 'clarity.priorities' => 4]);
    $before->update(['closed_at' => '2026-01-10 12:00:00']);
    $now = climateResultsSurvey($this->tenant, 30, fn (int $i): array => ['eng.pride' => 4, 'eng.motivation' => 4, 'eng.connection' => 4, 'clarity.goals' => 4, 'clarity.contribution' => 4, 'clarity.priorities' => 4]);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $now))
        ->assertInertia(fn (Assert $page) => $page
            ->where('comparison.name', $before->name)
            ->where('indexDelta.points', 17)
            ->where('indexDelta.direction', 'up')
            ->where('drivers.0.delta.points', 0)
            ->where('drivers.0.delta.direction', 'same'));
});

it('lists the priorities with their level and no figure', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);
    $survey->update(['priorities' => [['driver' => 'recognition', 'level' => 'high'], ['driver' => 'clarity', 'level' => 'medium']]]);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('priorities', [
                ['driver' => 'recognition', 'label' => Driver::Recognition->label(), 'level' => 'high', 'levelLabel' => 'Alta'],
                ['driver' => 'clarity', 'label' => Driver::Clarity->label(), 'level' => 'medium', 'levelLabel' => 'Media'],
            ]));
});

it('writes a log and an audit entry that say which survey and cut and carry no figure', function (): void {
    Log::spy();
    $survey = climateResultsSurvey($this->tenant, 6, segment: ['area_id' => Area::factory()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))->assertOk();

    $entry = AuditEntry::query()->where('event', 'engagement.results_viewed')->sole();
    expect($entry->subject_id)->toBe($survey->id)
        ->and($entry->metadata)->toBe(['cut' => 'area']);
    Log::shouldHaveReceived('info')->with('engagement.results_viewed', ['survey_id' => $survey->id, 'cut' => 'area'])->once();
});

it('puts no individual response in the props, for the company and for every cut', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $survey = climateResultsSurvey($this->tenant, 9, segment: ['work_center_id' => $plant->id, 'area_id' => $area->id, 'tenure_band' => '1to3']);
    App\Modules\Engagement\Models\Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'body' => 'Mi jefe grita en la bodega']);

    $this->actingAs($this->hr);
    foreach (['', '?cut=work_center', '?cut=area', '?cut=tenure', '?cut=manager'] as $query) {
        $this->get(climateResultsUrl($this->tenant, $survey, $query))
            ->assertInertia(fn (Assert $page) => expectNoEngagementResponses($page->toArray()['props']));
    }
});

it('never writes the forbidden name of the recommendation metric, in the props or in the code of the page', function (): void {
    $survey = climateResultsSurvey($this->tenant, 30);
    $forbidden = ['e'.'NPS', 'Net '.'Promoter'];

    $props = json_encode($this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey))->viewData('page')['props'], JSON_THROW_ON_ERROR);
    $code = '';
    foreach (array_filter([resource_path('js/pages/engagement'), resource_path('js/components/engagement'), resource_path('js/lib/engagement'), app_path('Modules/Engagement')], is_dir(...)) as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $code .= file_get_contents($file->getPathname());
        }
    }

    foreach ($forbidden as $word) {
        expect(mb_stripos($props, $word))->toBeFalse()
            ->and(mb_stripos($code, $word))->toBeFalse();
    }
});

it('reads in a constant number of queries however many responses and groups there are', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $survey = climateResultsSurvey($this->tenant, 40, segment: ['area_id' => $area->id]);
    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $this->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))->assertOk();
    $small = $queries;

    foreach (range(1, 3) as $ignored) {
        $other = Area::factory()->create(['tenant_id' => $this->tenant->id]);
        foreach (range(1, 6) as $ignoredToo) {
            Response::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'area_id' => $other->id, 'answers' => ['clarity.goals' => 5]]);
        }
    }
    $queries = 0;
    $this->get(climateResultsUrl($this->tenant, $survey, '?cut=area'))->assertOk();

    expect($queries)->toBe($small);
});

it('says the day the survey closed, by the calendar of the company, and how many commitments it has', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6);
    $survey->update(['closed_at' => '2026-06-23 03:00:00']);
    App\Modules\Engagement\Models\Commitment::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id]);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey))->assertInertia(fn (Assert $page) => $page
        ->where('survey.closedOn', '2026-06-22')->where('commitments', 2));
});

it('has no commitments to count on a survey still open', function (): void {
    $survey = climateResultsSurvey($this->tenant, 6, closed: false);

    $this->actingAs($this->hr)->get(climateResultsUrl($this->tenant, $survey))->assertInertia(fn (Assert $page) => $page
        ->where('survey.closedOn', null)->where('commitments', 0));
});

it('reads the company results in a fixed number of queries', function (): void {
    $survey = climateResultsSurvey($this->tenant, 12);
    $this->actingAs($this->hr);
    // The session, the company and the panel frame, the survey, its responses, the previous survey and its commitments.
    $this->expectsDatabaseQueryCount(16);

    $this->get(climateResultsUrl($this->tenant, $survey))->assertOk();
});
