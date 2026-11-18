<?php

declare(strict_types=1);

use App\Modules\Campaigns\Http\Controllers\SurveyController;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SurveyController::class);

// Whoever answers an evaluation is told the minimum of people per group it froze at launch: the page says it before each
// anonymous guide, and with 1 (no minimum) it warns instead of promising that small groups are hidden.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01 15:00:00');
    $this->tenant = Tenant::factory()->create(['min_group' => 7]);
});

/** A person invited to an active evaluation of the company that froze `$minimum`, with their link `the-token`. */
function invitedWithMinimum(Tenant $tenant, ?int $minimum): void
{
    $campaign = Campaign::factory()->active('2026-11-14')->create(['tenant_id' => $tenant->id, 'min_group' => $minimum]);
    $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'token_hash' => Participant::hashToken('the-token')]);
    ParticipantInstrument::factory()->create(['participant_id' => $participant->id, 'instrument' => 'nom035.guide-ii']);
}

it('gives the survey the minimum its evaluation froze, not the company minimum of now', function (int $minimum): void {
    invitedWithMinimum($this->tenant, $minimum);

    $response = $this->get(route('survey.show', 'the-token'))
        ->assertInertia(fn (Assert $page) => $page->component('campaigns/survey')->where('minGroup', $minimum));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('answers');
})->with([1, 3, 12]);

it('gives the platform minimum to an evaluation launched before companies chose their own', function (): void {
    invitedWithMinimum($this->tenant, null);

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('minGroup', 5));
});

it('gives no minimum when the link opens nothing', function (): void {
    $this->get(route('survey.show', 'nobody'))->assertInertia(fn (Assert $page) => $page->where('unavailable', 'invalid')->where('minGroup', null));
});
