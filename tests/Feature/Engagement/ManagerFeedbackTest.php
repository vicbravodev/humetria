<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Results\ManagerFeedback;
use App\Modules\Engagement\Support\Results\ManagerFeedbackData;
use App\Modules\Tenancy\Models\Tenant;

mutates(ManagerFeedback::class);

const FEEDBACK_BOSS_A = '0d3f1b6c-5d5e-4b0e-8a3d-2f7a9c1d4e10';
const FEEDBACK_BOSS_B = '1e4a2c7d-6e6f-4c1f-9b4e-3a8b0d2e5f21';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

it('says how the people of a manager answer, question by question, against the company', function (): void {
    $survey = climateManagerSurvey($this->tenant, [FEEDBACK_BOSS_A => [6, 5], FEEDBACK_BOSS_B => [6, 1]]);

    $feedback = ManagerFeedback::for($survey, FEEDBACK_BOSS_A);

    expect($feedback)->toBeInstanceOf(ManagerFeedbackData::class)
        ->and($feedback->items)->toHaveCount(8)
        ->and($feedback->items[0])->toBe(['key' => 'manager.priorities', 'text' => 'Mi jefe me aclara las prioridades cuando tengo varias tareas pendientes.', 'favorable' => 100, 'vsCompany' => 50])
        ->and($feedback->strengths)->toHaveCount(2)
        ->and($feedback->opportunities)->toHaveCount(2)
        ->and($feedback->line)->toBeFalse()
        ->and($feedback->summaryStatus)->toBeNull()
        ->and($feedback->themes)->toBeNull();

    $other = ManagerFeedback::for($survey, FEEDBACK_BOSS_B);
    expect($other?->items[0]['favorable'])->toBe(0)->and($other->items[0]['vsCompany'])->toBe(-50);
});

it('gives nothing while the survey is not closed', function (): void {
    $survey = climateManagerSurvey($this->tenant, [FEEDBACK_BOSS_A => [6, 5]]);
    $survey->update(['status' => 'active', 'closed_at' => null]);

    expect(ManagerFeedback::for($survey->refresh(), FEEDBACK_BOSS_A))->toBeNull();
});

it('gives nothing for a group under the minimum, for one the complementary suppression hid, and for a stranger', function (): void {
    // 6 and 5 are shown; 4 is under the minimum, so it goes to the rest and the smallest shown (5) is hidden with it.
    $survey = climateManagerSurvey($this->tenant, [FEEDBACK_BOSS_A => [6, 5], FEEDBACK_BOSS_B => [5, 5], '2f5b3d8e-7f70-4d2a-8c5f-4b9c1e3f6a32' => [4, 5]]);

    expect(ManagerFeedback::for($survey, FEEDBACK_BOSS_A))->not->toBeNull()
        ->and(ManagerFeedback::for($survey, FEEDBACK_BOSS_B))->toBeNull()
        ->and(ManagerFeedback::for($survey, '2f5b3d8e-7f70-4d2a-8c5f-4b9c1e3f6a32'))->toBeNull()
        ->and(ManagerFeedback::for($survey, '3a6c4e9f-8a81-4e3b-9d60-5c0d2f4a7b43'))->toBeNull();
});

it('leaves out a question that fewer than the minimum answered, and gives nothing when none is left', function (): void {
    $survey = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    foreach (range(1, 6) as $i) {
        App\Modules\Engagement\Models\Response::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => FEEDBACK_BOSS_A, 'answers' => ['manager.respect' => 5, 'manager.listening' => $i <= 4 ? 5 : null]]);
    }

    $feedback = ManagerFeedback::for($survey, FEEDBACK_BOSS_A);
    expect(array_column($feedback->items ?? [], 'key'))->toBe(['manager.respect']);

    $silent = Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    App\Modules\Engagement\Models\Response::factory()->count(6)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $silent->id, 'manager_group_id' => FEEDBACK_BOSS_A, 'answers' => ['manager.respect' => null]]);

    expect(ManagerFeedback::for($silent, FEEDBACK_BOSS_A))->toBeNull();
});

it('says the result is of the whole team when someone in the group has another direct manager', function (): void {
    $boss = App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = App\Modules\Organization\Models\Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $survey = climateManagerSurvey($this->tenant, [$boss->id => [6, 5]]);
    Invitation::factory()->create(['survey_id' => $survey->id, 'tenant_id' => $this->tenant->id, 'manager_group_id' => $boss->id, 'about_manager_id' => $boss->id]);

    expect(ManagerFeedback::for($survey, $boss->id)?->line)->toBeFalse();

    Invitation::factory()->create(['survey_id' => $survey->id, 'tenant_id' => $this->tenant->id, 'manager_group_id' => $boss->id, 'about_manager_id' => $other->id]);

    expect(ManagerFeedback::for($survey, $boss->id)?->line)->toBeTrue();
});

it('carries the AI summary of the group of comments of the manager, and its status when there is none', function (SummaryStatus $status): void {
    $survey = climateManagerSurvey($this->tenant, [FEEDBACK_BOSS_A => [6, 5]]);
    CommentSummary::factory()->status($status)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => FEEDBACK_BOSS_A]);

    $feedback = ManagerFeedback::for($survey, FEEDBACK_BOSS_A);

    expect($feedback?->summaryStatus)->toBe($status)->and($feedback->themes)->toBeNull();
})->with([SummaryStatus::Pending, SummaryStatus::Failed, SummaryStatus::AiOff, SummaryStatus::Withheld]);

it('carries the themes of a ready summary and never the summary of another group', function (): void {
    $survey = climateManagerSurvey($this->tenant, [FEEDBACK_BOSS_A => [6, 5], FEEDBACK_BOSS_B => [6, 5]]);
    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => FEEDBACK_BOSS_B, 'themes' => ['valued' => [['theme' => 'Del otro jefe', 'count' => 4]], 'change' => []]]);

    expect(ManagerFeedback::for($survey, FEEDBACK_BOSS_A)?->summaryStatus)->toBeNull();

    CommentSummary::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $survey->id, 'manager_group_id' => FEEDBACK_BOSS_A]);
    $feedback = ManagerFeedback::for($survey, FEEDBACK_BOSS_A);

    expect($feedback?->summaryStatus)->toBe(SummaryStatus::Ready)
        ->and($feedback->themes['valued'][0]['theme'] ?? null)->toBe('Buen trato entre compañeros');
});

it('has no number of how many were invited or answered', function (): void {
    $properties = array_map(fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(ManagerFeedbackData::class))->getProperties());

    expect($properties)->toBe(['items', 'strengths', 'opportunities', 'line', 'summaryStatus', 'themes']);
});
