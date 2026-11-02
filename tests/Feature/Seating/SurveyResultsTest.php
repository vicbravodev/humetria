<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Support\AnswerDistribution;
use App\Modules\Seating\Http\Controllers\SurveyResultsController;
use App\Modules\Seating\Support\SurveyPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AnswerDistribution::class, SurveyPages::class, SurveyResultsController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Bus::fake();
    config(['results.min_group' => 5]);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 2);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->actingAs($this->hr)->post(route('tenant.seating.survey.launch', [$this->tenant, $this->plant->id]), ['closes_on' => '2026-11-12'])->assertSessionHasNoErrors();
    $this->survey = Campaign::query()->sole();
    $this->north = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Almacén']);
    $this->south = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Empaque']);
    $this->east = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Oficinas']);
});

function answerSurvey(Campaign $survey, Area $area, int $times, array $answers): void
{
    AnonymousResponse::factory()->count($times)->create([
        'campaign_id' => $survey->id,
        'area_id' => $area->id,
        'instrument' => 'seating.survey',
        'instrument_version' => 'dof-2025-07-17',
        'answers' => $answers,
    ]);
}

function closeSeatingSurvey(Campaign $survey): void
{
    $survey->update(['status' => 'closed', 'closed_at' => now()]);
}

function surveyResultsPage(mixed $test, string $query = ''): Illuminate\Testing\TestResponse
{
    return $test->get(route('tenant.seating.survey', [$test->tenant, $test->plant->id]).$query);
}

it('hides a group of four and shows five', function (int $answered, bool $visible): void {
    answerSurvey($this->survey, $this->north, $answered, surveyAnswers('yes'));
    closeSeatingSurvey($this->survey);

    surveyResultsPage($this->actingAs($this->hr))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/survey')
            ->where('results.visible', $visible)
            ->where('results.answered', $answered)
            ->where('results.questions', fn ($questions) => ($questions->count() > 0) === $visible));
})->with([[4, false], [5, true]]);

it('counts yes, no and not applicable of each question', function (): void {
    answerSurvey($this->survey, $this->north, 3, surveyAnswers('yes'));
    answerSurvey($this->survey, $this->north, 2, surveyAnswers('no'));
    answerSurvey($this->survey, $this->south, 1, surveyAnswers('not_applicable'));
    closeSeatingSurvey($this->survey);

    surveyResultsPage($this->actingAs($this->hr))->assertInertia(fn (Assert $page) => $page
        ->where('results.answered', 6)
        ->where('results.questions.0', fn ($question) => $question['code'] === 'SS-01' && $question['visible'] && [$question['yes'], $question['no'], $question['notApplicable']] === [3, 2, 1]));
});

it('reads the items nobody answered behind a «no» to standing as no data, and hides a question few answered', function (): void {
    answerSurvey($this->survey, $this->north, 6, ['stands' => 'no']);
    answerSurvey($this->survey, $this->north, 4, surveyAnswers('yes'));
    closeSeatingSurvey($this->survey);

    surveyResultsPage($this->actingAs($this->hr))->assertInertia(fn (Assert $page) => $page
        ->where('results.answered', 10)
        ->where('results.visible', true)
        // Only four stand: the question shows no count at all, not even that four answered it.
        ->where('results.questions.0', fn ($question) => $question['visible'] === false && ! collect($question)->has('yes')));
});

it('applies the minimum after the area filter', function (): void {
    answerSurvey($this->survey, $this->north, 8, surveyAnswers('yes'));
    answerSurvey($this->survey, $this->south, 4, surveyAnswers('no'));
    answerSurvey($this->survey, $this->east, 5, surveyAnswers('yes'));
    closeSeatingSurvey($this->survey);

    // The area of eight is shown. The area of four is not an area to filter by: asking for it gives the whole center, never
    // the four.
    surveyResultsPage($this->actingAs($this->hr), '?area='.$this->north->id)->assertInertia(fn (Assert $page) => $page
        ->where('results.answered', 8)
        ->where('results.area.label', 'Almacén')
        ->where('results.questions.0.yes', 8));
    surveyResultsPage($this->actingAs($this->hr), '?area='.$this->south->id)->assertInertia(fn (Assert $page) => $page
        ->where('results.answered', 17)
        ->where('results.area', null)
        ->where('results.questions.0.no', 4));
});

it('never reveals a hidden area by subtracting subtotals from the total', function (): void {
    answerSurvey($this->survey, $this->north, 8, surveyAnswers('yes'));
    answerSurvey($this->survey, $this->south, 2, surveyAnswers('no'));
    answerSurvey($this->survey, $this->east, 5, surveyAnswers('yes'));
    closeSeatingSurvey($this->survey);

    // Empaque (2) is hidden; Oficinas (5) goes with it, so what stays hidden (7) is enough: 15 - 8 never gives 2.
    surveyResultsPage($this->actingAs($this->hr))->assertInertia(fn (Assert $page) => $page
        ->where('results.areas.hidden', 2)
        ->where('results.areas.visible', fn ($areas) => $areas->pluck('name')->all() === ['Almacén'] && $areas->pluck('answered')->all() === [8]));
});

it('shows no response time and no individual answer', function (): void {
    answerSurvey($this->survey, $this->north, 6, surveyAnswers('no'));
    closeSeatingSurvey($this->survey);

    $props = surveyResultsPage($this->actingAs($this->hr))->viewData('page')['props'];
    $json = (string) json_encode(['survey' => $props['survey'], 'results' => $props['results']]);

    expect($json)->not->toContain('created_at')->not->toContain('answered_at')->not->toContain('"answers"')
        ->not->toContain(AnonymousResponse::query()->first()->id);
});

it('shows only the progress while the survey is open', function (): void {
    answerSurvey($this->survey, $this->north, 6, surveyAnswers('yes'));

    surveyResultsPage($this->actingAs($this->hr))->assertInertia(fn (Assert $page) => $page
        ->where('survey.status', 'active')
        ->where('survey.closesOn', '2026-11-12')
        ->where('results', null));
});

it('has nothing to show before a survey is launched', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs($this->hr)->get(route('tenant.seating.survey', [$this->tenant, $other->id]))->assertInertia(fn (Assert $page) => $page
        ->where('survey', null)
        ->where('results', null)
        ->where('canLaunch', true));
});

it('records in the audit trail that the results were viewed, with no answer', function (): void {
    answerSurvey($this->survey, $this->north, 6, surveyAnswers('yes'));
    closeSeatingSurvey($this->survey);

    surveyResultsPage($this->actingAs($this->hr));

    $entry = AuditEntry::query()->where('event', 'seating.survey_results_viewed')->sole();
    expect($entry->metadata)->toEqual(['campaign_id' => $this->survey->id, 'work_center_id' => $this->plant->id, 'area_id' => null]);
});

it('answers 403 to the committee and the viewer, and 404 for a center of another company', function (): void {
    foreach ([TenantRole::Committee, TenantRole::Viewer] as $role) {
        surveyResultsPage($this->actingAs(memberOf($this->tenant, $role)))->assertForbidden();
    }

    $foreign = Tenant::factory()->create(['slug' => 'other']);
    $center = WorkCenter::factory()->for($foreign)->create();

    $this->actingAs($this->hr)->get(route('tenant.seating.survey', [$this->tenant, $center->id]))->assertNotFound();
});
