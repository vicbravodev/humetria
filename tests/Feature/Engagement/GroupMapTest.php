<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\ResultsPresenter;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

// The map by group of Clima (groups × themes) under the three rules of its approval: a group under the minimum and one
// hidden so it does not give the first away look the same, nothing says how many answered nor how many are missing, and
// it never crosses two attributes.

mutates(ResultsPresenter::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/** Every agreement question answered 4, recognition's criteria 1: recognition scores 100 and 0, 50 on the map. */
function groupMapAnswers(): array
{
    $answers = [];
    foreach (ItemBank::all() as $item) {
        if ($item->scale !== ItemScale::Open) {
            $answers[$item->key] = $item->scale === ItemScale::Recommend ? 9 : ($item->key === 'recognition.criteria' ? 1 : 4);
        }
    }

    return $answers;
}

/** Answers of `$count` people of `$center` in `$survey`. */
function groupMapResponses(Survey $survey, WorkCenter $center, int $count): void
{
    Response::factory()->count($count)->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id, 'work_center_id' => $center->id, 'answers' => groupMapAnswers()]);
}

it('scores a theme reported question by question as the simple average of its questions, for the company and each group', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'invited' => 20]);
    groupMapResponses($survey, $plant, 12);

    $this->actingAs($this->hr)->get(route('tenant.climate.results', [$this->tenant, $survey]).'?cut=work_center')->assertInertia(fn (Assert $page) => $page
        ->where('drivers.3.key', 'recognition')->where('drivers.3.favorable', null)->where('drivers.3.score', 50)
        ->where('drivers.0.key', 'clarity')->where('drivers.0.score', 100)
        ->where('groups.0.drivers.3.key', 'recognition')->where('groups.0.drivers.3.score', 50));
});

it('has no score for a theme with a question under the minimum', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'invited' => 20]);
    groupMapResponses($survey, $plant, 4);

    $this->actingAs($this->hr)->get(route('tenant.climate.results', [$this->tenant, $survey]))->assertInertia(fn (Assert $page) => $page
        ->where('drivers.3.score', null)->where('drivers.0.score', null));
});

it('draws a group under the minimum and one hidden by complement alike: neither is in the props, only that some are protected', function (): void {
    $plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $branch = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Sucursal Sur']);
    $tiny = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Bodega Chica']);
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'invited' => 40]);
    groupMapResponses($survey, $plant, 20);
    // Six answer in the branch, enough on its own; three in the warehouse, under the minimum. Hiding only the warehouse
    // would let the company minus the rest point at it, so the branch is hidden too.
    groupMapResponses($survey, $branch, 6);
    groupMapResponses($survey, $tiny, 3);

    $response = $this->actingAs($this->hr)->get(route('tenant.climate.results', [$this->tenant, $survey]).'?cut=work_center');
    $response->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)->where('groups.0.name', 'Planta Norte')->where('hasHidden', true));

    $props = $response->inertiaProps();
    $json = json_encode($props, JSON_THROW_ON_ERROR);
    expect($json)->not->toContain($branch->id)->not->toContain($tiny->id)
        ->not->toContain('Sucursal Sur')->not->toContain('Bodega Chica')
        ->and(array_keys($props))->not->toContain('hiddenGroups', 'hiddenCount', 'suppressed', 'missing')
        ->and(array_keys($props['groups'][0]))->toBe(['key', 'name', 'responses', 'index', 'vsCompany', 'recommendation', 'drivers', 'strengths', 'opportunities']);
    // What the company shows is all of it: 29 answered, never "9 are not shown".
    expect($props['participation']['responses'])->toBe(29)
        ->and(array_sum(array_column($props['groups'], 'responses')))->toBe(20)
        ->and($json)->not->toContain('"missing')->not->toContain('"hidden"');
    expectNoEngagementResponses($props);
});

it('says nothing of the managers it hides, not even how many', function (): void {
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'invited' => 40]);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    groupMapResponses($survey, $plant, 20);

    $props = $this->actingAs($this->hr)->get(route('tenant.climate.results', [$this->tenant, $survey]).'?cut=manager')->inertiaProps();

    expect($props['groups'])->toBe([])
        ->and(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('managersHidden')->not->toContain('hiddenManagers');
});

it('maps one attribute at a time and never two', function (string $query): void {
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->hr)->getJson(route('tenant.climate.results', [$this->tenant, $survey]).$query)->assertUnprocessable();
})->with([
    'two cuts' => '?cut[]=work_center&cut[]=area',
    'two joined' => '?cut=work_center,area',
    'a cut by two' => '?cut=work_center%2Barea',
]);
