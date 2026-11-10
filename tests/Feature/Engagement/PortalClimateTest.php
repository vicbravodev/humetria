<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Http\Controllers\PortalClimateController;
use App\Modules\Engagement\Models\Comment;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalClimateController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->stock = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bodega']);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->sales->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    workIn($this->tenant);
    $this->token = resolve(PortalAccess::class)->token($this->ana->id);
    $this->survey = climateResultsSurvey($this->tenant, 12);
    $this->survey->update(['invited' => 20, 'results_published_at' => now()]);
    Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->sales->id]);
});

function portalCommitment(array $state = []): Commitment
{
    return Commitment::factory()->create([...$state, 'tenant_id' => test()->tenant->id, 'survey_id' => test()->survey->id]);
}

it('lives at /portal/{token}/climate, throttled like the rest of the portal', function (): void {
    expect(route('portal.climate.show', 'abc', absolute: false))->toBe('/portal/abc/climate')
        ->and(Route::getRoutes()->getByName('portal.climate.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

it('shows the person what the company said it will do: the company and their own groups, not others', function (): void {
    portalCommitment(['will_do' => 'Acción de toda la empresa']);
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id, 'will_do' => 'Acción de Ventas']);
    portalCommitment(['group_type' => 'area', 'group_id' => $this->stock->id, 'will_do' => 'Acción de Bodega']);
    portalCommitment(['group_type' => 'work_center', 'group_id' => $this->plant->id, 'will_do' => 'Acción del centro']);
    portalCommitment(['will_do' => 'Acción descartada', 'status' => CommitmentStatus::Dropped]);

    $this->get(route('portal.climate.show', $this->token))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('engagement/portal-climate')
            ->where('company', 'Acme')
            ->where('name', 'Ana Ruiz')
            ->where('survey.name', $this->survey->name)
            ->where('participation.percent', 60)
            ->where('index', 100)
            ->has('commitments.data', 3)
            ->where('commitments.data', fn ($rows): bool => collect($rows)->pluck('will_do')->sort()->values()->all() === ['Acción de Ventas', 'Acción de toda la empresa', 'Acción del centro']));
});

it('lists the commitments of the group of the person\'s manager', function (): void {
    $manager = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    Invitation::query()->where('employee_id', $this->ana->id)->update(['manager_group_id' => $manager->id]);
    portalCommitment(['group_type' => 'manager', 'group_id' => $manager->id, 'will_do' => 'Acción del equipo']);
    portalCommitment(['group_type' => 'manager', 'group_id' => Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->id, 'will_do' => 'Acción de otro equipo']);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->has('commitments.data', 1)->where('commitments.data.0.will_do', 'Acción del equipo'));
});

it('shows only the company to someone who was not invited', function (): void {
    Invitation::query()->where('employee_id', $this->ana->id)->delete();
    portalCommitment(['will_do' => 'Acción de toda la empresa']);
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id]);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->has('commitments.data', 1)->where('commitments.data.0.group_name', 'Toda la empresa'));
});

it('pages the commitments', function (): void {
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id]);
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id]);
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id]);
    portalCommitment();
    portalCommitment();
    portalCommitment();
    portalCommitment(['group_type' => 'work_center', 'group_id' => $this->plant->id]);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->has('commitments', fn (Assert $list) => $list->hasAll(['data', 'current_page', 'last_page', 'per_page', 'total', 'prev_page_url', 'next_page_url', 'links'])->etc())->where('commitments.total', 7)->has('commitments.data', 5));
});

it('is not found with no published survey, with one not published yet, with a link that is not valid, or without commitments', function (): void {
    $this->get(route('portal.climate.show', 'not-a-token'))->assertNotFound();

    portalCommitment();
    $this->survey->update(['results_published_at' => null]);
    $this->get(route('portal.climate.show', $this->token))->assertNotFound();

    $this->survey->update(['results_published_at' => now()]);
    Commitment::query()->delete();
    $this->get(route('portal.climate.show', $this->token))->assertNotFound();
});

it('shows the latest published survey', function (): void {
    portalCommitment(['will_do' => 'Acción vieja']);
    $this->survey->update(['results_published_at' => now()->subYear()]);
    $newer = climateResultsSurvey($this->tenant, 12);
    $newer->update(['results_published_at' => now(), 'name' => 'Clima · la nueva']);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $newer->id, 'will_do' => 'Acción nueva']);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('survey.name', 'Clima · la nueva')->where('commitments.data.0.will_do', 'Acción nueva'));
});

it('does not use the survey of another company', function (): void {
    $other = Tenant::factory()->create();
    $surveyOfOther = climateResultsSurvey($other, 12);
    $surveyOfOther->update(['results_published_at' => now()->addDay()]);
    Commitment::factory()->create(['tenant_id' => $other->id, 'survey_id' => $surveyOfOther->id, 'will_do' => 'Acción ajena']);
    workIn($this->tenant);
    portalCommitment(['will_do' => 'Acción propia']);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('survey.name', $this->survey->name)->where('commitments.data.0.will_do', 'Acción propia'));
});

it('hides the participation and the index of a company too small to show', function (): void {
    $small = climateResultsSurvey($this->tenant, 4);
    $small->update(['results_published_at' => now()->addMinute(), 'invited' => 5]);
    Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $small->id]);

    $this->get(route('portal.climate.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('survey.name', $small->name)->where('participation', null)->where('index', null));
});

it('never carries a response, a comment or a result of a group', function (): void {
    Comment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'body' => 'Falta personal en la bodega']);
    portalCommitment();
    portalCommitment(['group_type' => 'area', 'group_id' => $this->sales->id]);

    $response = $this->get(route('portal.climate.show', $this->token))->assertOk();
    $props = Assert::fromTestResponse($response)->toArray()['props'];

    expectNoEngagementResponses($props);
    expect(array_keys($props))->not->toContain('groups')->not->toContain('drivers')->not->toContain('comments')->not->toContain('priorities')
        ->and(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('Net Promoter')->not->toContain('eNPS');
});
