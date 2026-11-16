<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignClock;
use App\Modules\Campaigns\Support\SurveyWorkerSection as CampaignsSurveySection;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ComplaintsWorkerSection;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Engagement\Support\SurveyWorkerSection as EngagementSurveySection;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ComplaintsWorkerSection::class, CampaignsSurveySection::class, EngagementSurveySection::class);

/*
 * One start for everything: the person's start lists the evaluation, the climate survey and the complaints channel next to
 * what it already listed, each as a link of its own module (see docs/superpowers/specs/2026-11-14-single-personal-link-design.md).
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
});

/** An invited person of the center's campaign with a guide left, and the link of their email (its hash is stored). */
function singleLinkEvaluationInvitation(Employee $person, Campaign $campaign, bool $guideLeft = true): Participant
{
    $participant = Participant::factory()->create(['tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'employee_id' => $person->id]);
    $participant->forceFill(['token_hash' => Participant::hashToken($participant->surveyToken())])->save();
    $guide = ParticipantInstrument::factory()->state(['tenant_id' => $campaign->tenant_id, 'participant_id' => $participant->id]);
    ($guideLeft ? $guide : $guide->completed())->create();

    return $participant;
}

/** @return array<string, mixed> The `sections` of the start of Ana, in her session, keyed by their key. */
function singleLinkStartSections(): array
{
    $sections = [];
    test()->get(route('worker.home', asPerson(test()->ana->id)))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $sections = collect($page->toArray()['props']['sections'])->keyBy('key')->all();
    });

    return $sections;
}

function singleLinkClimateInvitation(Employee $person, Survey $survey, array $state = []): Invitation
{
    $invitation = Invitation::factory()->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id, 'employee_id' => $person->id, ...$state]);
    $invitation->forceFill(['token_hash' => Invitation::hashToken($invitation->surveyToken())])->save();

    return $invitation;
}

describe('complaints channel', function (): void {
    it('links the public channel of the company, with nothing of the person in it', function (): void {
        $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();

        $section = singleLinkStartSections()['complaints'];

        expect($section['href'])->toBe($channel->publicUrl())
            ->and($section['label'])->toBe('Quejas y denuncias')
            ->and($section['pending'])->toBeNull()
            ->and($section['href'])->not->toContain($this->ana->id);
    });

    it('does not offer a channel that takes nothing, a paused one, one that does not exist or a company without the module', function (): void {
        expect(singleLinkStartSections())->not->toHaveKey('complaints');

        $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->create();
        expect(singleLinkStartSections())->not->toHaveKey('complaints');

        $channel->forceFill(['enabled_at' => now(), 'published_on' => now()->toDateString(), 'paused_at' => now()])->save();
        expect(singleLinkStartSections())->not->toHaveKey('complaints');

        $channel->forceFill(['paused_at' => null])->save();
        expect(singleLinkStartSections())->toHaveKey('complaints');

        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkStartSections())->not->toHaveKey('complaints');
    });
});

describe('NOM-035 evaluation', function (): void {
    it('sends the person to their own survey link while the campaign is open and a guide is left', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $participant = singleLinkEvaluationInvitation($this->ana, $campaign);

        $section = singleLinkStartSections()['survey'];

        expect($section['href'])->toBe(route('survey.show', $participant->surveyToken()))
            ->and($section['label'])->toBe('Su evaluación NOM-035')
            ->and($section['pending'])->toBe(1);
    });

    it('offers nothing once the guides are answered, the campaign is closed, the link was not sent or the module is off', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $participant = singleLinkEvaluationInvitation($this->ana, $campaign, guideLeft: false);
        expect(singleLinkStartSections())->not->toHaveKey('survey');

        ParticipantInstrument::factory()->create(['tenant_id' => $this->tenant->id, 'participant_id' => $participant->id, 'instrument' => 'nom035.guide-ii']);
        expect(singleLinkStartSections())->toHaveKey('survey');

        $participant->forceFill(['token_hash' => null])->save();
        expect(singleLinkStartSections())->not->toHaveKey('survey');

        $participant->forceFill(['token_hash' => Participant::hashToken($participant->surveyToken())])->save();
        $campaign->update(['closes_on' => CampaignClock::today()->subDay()->toDateString()]);
        expect(singleLinkStartSections())->not->toHaveKey('survey');

        $campaign->update(['closes_on' => CampaignClock::today()->addDay()->toDateString()]);
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkStartSections())->not->toHaveKey('survey');
    });

    it('never offers the survey of somebody else', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $theirs = singleLinkEvaluationInvitation($beto, $campaign);

        $this->get(route('worker.home', asPerson($this->ana->id)))->assertInertia(function (Assert $page) use ($theirs): void {
            expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain($theirs->surveyToken());
        });
        expect(singleLinkStartSections())->not->toHaveKey('survey');
    });

    it('does not count a campaign that is a draft', function (): void {
        $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        singleLinkEvaluationInvitation($this->ana, $campaign);

        expect(singleLinkStartSections())->not->toHaveKey('survey');
    });
});

describe('climate and pulse survey, in the person\'s start', function (): void {
    it('sends the person to their own link while the survey is open and they have not answered', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $invitation = singleLinkClimateInvitation($this->ana, $survey);

        $section = singleLinkStartSections()['engagement'];

        expect($section['href'])->toBe(route('engagement.survey.show', $invitation->surveyToken()))
            ->and($section['label'])->toBe('Encuesta de clima')
            ->and($section['pending'])->toBe(1);
    });

    it('calls a pulse by its name', function (): void {
        $survey = Survey::factory()->pulse()->active()->create(['tenant_id' => $this->tenant->id]);
        singleLinkClimateInvitation($this->ana, $survey);

        expect(singleLinkStartSections()['engagement']['label'])->toBe('Pulso');
    });

    it('offers nothing when it was answered, cancelled, not sent, closed, still a draft or the module is off', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $invitation = singleLinkClimateInvitation($this->ana, $survey, ['answered' => true]);
        expect(singleLinkStartSections())->not->toHaveKey('engagement');

        $invitation->forceFill(['answered' => false, 'cancelled' => true])->save();
        expect(singleLinkStartSections())->not->toHaveKey('engagement');

        $invitation->forceFill(['cancelled' => false, 'token_hash' => null])->save();
        expect(singleLinkStartSections())->not->toHaveKey('engagement');

        $invitation->forceFill(['token_hash' => Invitation::hashToken($invitation->surveyToken())])->save();
        expect(singleLinkStartSections())->toHaveKey('engagement');

        $survey->update(['closes_on' => SurveyClock::day()->subDay()->toDateString()]);
        expect(singleLinkStartSections())->not->toHaveKey('engagement');

        $survey->update(['closes_on' => SurveyClock::day()->addDay()->toDateString(), 'status' => 'draft']);
        expect(singleLinkStartSections())->not->toHaveKey('engagement');

        $survey->update(['status' => 'active']);
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkStartSections())->not->toHaveKey('engagement');
    });

    it('never offers the survey of somebody else', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $theirs = singleLinkClimateInvitation($beto, $survey);

        $this->get(route('worker.home', asPerson($this->ana->id)))->assertInertia(function (Assert $page) use ($theirs): void {
            expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain($theirs->surveyToken());
        });
        expect(singleLinkStartSections())->not->toHaveKey('engagement');
    });

});
