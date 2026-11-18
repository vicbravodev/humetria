<?php

declare(strict_types=1);

use App\Modules\Engagement\Contracts\EngagementStanding;
use App\Modules\Engagement\Data\SurveyStanding;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\CompanyEngagementStanding;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyEngagementStanding::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();
    $this->standing = resolve(EngagementStanding::class);
});

/** A survey of `$tenant` with `$answered` people who answered (and one who did not, and one cancelled). */
function surveyAnsweredBy(Survey $survey, int $answered): Survey
{
    Invitation::factory()->answered()->count($answered)->create(['survey_id' => $survey->id]);
    Invitation::factory()->create(['survey_id' => $survey->id]);
    Invitation::factory()->cancelled()->create(['survey_id' => $survey->id]);

    return $survey;
}

it('says nothing was launched while the company has no survey of the kind', function (): void {
    Survey::factory()->closed()->create(['tenant_id' => $this->a->id]);
    workIn($this->a);

    expect($this->standing->pulse())->toEqual(new SurveyStanding(false, null, 0, 0, null, false));
});

it('tells a draft waits to be launched', function (): void {
    Survey::factory()->pulse()->create(['tenant_id' => $this->a->id]);
    workIn($this->a);

    expect($this->standing->pulse())->toEqual(new SurveyStanding(true, null, 0, 0, null, false))
        ->and($this->standing->climate()->draft)->toBeFalse();
});

it('tells until when the open survey takes answers and how many of the invited answered, never of another company', function (): void {
    surveyAnsweredBy(Survey::factory()->active('2026-11-30')->create(['tenant_id' => $this->a->id, 'invited' => 80]), 3);
    surveyAnsweredBy(Survey::factory()->active('2026-12-15')->create(['tenant_id' => $this->b->id, 'invited' => 9]), 6);
    workIn($this->a);

    expect($this->standing->climate())->toEqual(new SurveyStanding(false, '2026-11-30', 80, 3, null, false));
});

it('tells the last closed survey shows its results only from the minimum of respondents', function (): void {
    surveyAnsweredBy(Survey::factory()->closed()->create(['tenant_id' => $this->a->id, 'closed_at' => '2026-10-01 18:00:00']), 9);
    $last = surveyAnsweredBy(Survey::factory()->closed()->create(['tenant_id' => $this->a->id, 'closed_at' => '2026-11-11 03:00:00']), 4);
    workIn($this->a);

    // Closed at 21:00 of the 10th in Mexico City.
    expect($this->standing->climate())->toEqual(new SurveyStanding(false, null, 0, 0, '2026-11-10', false));

    Invitation::factory()->answered()->create(['survey_id' => $last->id]);

    expect($this->standing->climate()->lastResultsShown)->toBeTrue();
});

it('follows a stricter minimum of respondents', function (): void {
    config(['results.min_group' => 6]);
    surveyAnsweredBy(Survey::factory()->pulse()->closed()->create(['tenant_id' => $this->a->id]), 5);
    workIn($this->a);

    expect($this->standing->pulse()->lastResultsShown)->toBeFalse();
});
