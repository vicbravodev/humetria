<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\SurveyPortalSection as CampaignsSurveySection;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ComplaintsPortalSection;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyPortalSection as EngagementSurveySection;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ComplaintsPortalSection::class, CampaignsSurveySection::class, EngagementSurveySection::class);

/*
 * One link for everything: the portal lists the evaluation, the climate survey and the complaints channel next to what it
 * already listed, each as a link of its own module (see docs/superpowers/specs/2026-11-14-single-personal-link-design.md).
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

/** @return array<string, mixed> The `sections` of the home page, keyed by their key. */
function singleLinkHomeSections(string $token): array
{
    $sections = [];
    test()->get(route('portal.show', $token))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $sections = collect($page->toArray()['props']['sections'])->keyBy('key')->all();
    });

    return $sections;
}

/** An invited person of the center's campaign with a guide left, and the link of their email (its hash is stored). */
function singleLinkEvaluationInvitation(Employee $person, Campaign $campaign, bool $guideLeft = true): Participant
{
    $participant = Participant::factory()->create(['tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'employee_id' => $person->id]);
    $participant->forceFill(['token_hash' => Participant::hashToken($participant->surveyToken())])->save();
    $guide = ParticipantInstrument::factory()->state(['tenant_id' => $campaign->tenant_id, 'participant_id' => $participant->id]);
    ($guideLeft ? $guide : $guide->completed())->create();

    return $participant;
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

        $section = singleLinkHomeSections($this->token)['complaints'];

        expect($section['href'])->toBe($channel->publicUrl())
            ->and($section['label'])->toBe('Quejas y denuncias')
            ->and($section['pending'])->toBeNull()
            ->and($section['href'])->not->toContain($this->token)->not->toContain($this->ana->id);
    });

    it('does not offer a channel that takes nothing, a paused one, one that does not exist or a company without the module', function (): void {
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('complaints');

        $channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->create();
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('complaints');

        $channel->forceFill(['enabled_at' => now(), 'published_on' => now()->toDateString(), 'paused_at' => now()])->save();
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('complaints');

        $channel->forceFill(['paused_at' => null])->save();
        expect(singleLinkHomeSections($this->token))->toHaveKey('complaints');

        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['nom035'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('complaints');
    });
});

describe('NOM-035 evaluation', function (): void {
    it('sends the person to their own survey link while the campaign is open and a guide is left', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $participant = singleLinkEvaluationInvitation($this->ana, $campaign);

        $section = singleLinkHomeSections($this->token)['survey'];

        expect($section['href'])->toBe(route('survey.show', $participant->surveyToken()))
            ->and($section['label'])->toBe('Su evaluación NOM-035')
            ->and($section['pending'])->toBe(1);
    });

    it('offers nothing once the guides are answered, the campaign is closed, the link was not sent or the module is off', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $participant = singleLinkEvaluationInvitation($this->ana, $campaign, guideLeft: false);
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');

        ParticipantInstrument::factory()->create(['tenant_id' => $this->tenant->id, 'participant_id' => $participant->id, 'instrument' => 'nom035.guide-ii']);
        expect(singleLinkHomeSections($this->token))->toHaveKey('survey');

        $participant->forceFill(['token_hash' => null])->save();
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');

        $participant->forceFill(['token_hash' => Participant::hashToken($participant->surveyToken())])->save();
        $campaign->update(['closes_on' => now()->subDay()->toDateString()]);
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');

        $campaign->update(['closes_on' => now()->addDay()->toDateString()]);
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');
    });

    it('never offers the survey of somebody else', function (): void {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        $theirs = singleLinkEvaluationInvitation($beto, $campaign);

        $this->get(route('portal.show', $this->token))->assertInertia(function (Assert $page) use ($theirs): void {
            expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain($theirs->surveyToken());
        });
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');
    });

    it('does not count a campaign that is a draft', function (): void {
        $campaign = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        singleLinkEvaluationInvitation($this->ana, $campaign);

        expect(singleLinkHomeSections($this->token))->not->toHaveKey('survey');
    });
});

describe('climate and pulse survey', function (): void {
    it('sends the person to their own link while the survey is open and they have not answered', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $invitation = singleLinkClimateInvitation($this->ana, $survey);

        $section = singleLinkHomeSections($this->token)['engagement'];

        expect($section['href'])->toBe(route('engagement.survey.show', $invitation->surveyToken()))
            ->and($section['label'])->toBe('Encuesta de clima')
            ->and($section['pending'])->toBe(1);
    });

    it('calls a pulse by its name', function (): void {
        $survey = Survey::factory()->pulse()->active()->create(['tenant_id' => $this->tenant->id]);
        singleLinkClimateInvitation($this->ana, $survey);

        expect(singleLinkHomeSections($this->token)['engagement']['label'])->toBe('Pulso');
    });

    it('offers nothing when it was answered, cancelled, not sent, closed, still a draft or the module is off', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $invitation = singleLinkClimateInvitation($this->ana, $survey, ['answered' => true]);
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');

        $invitation->forceFill(['answered' => false, 'cancelled' => true])->save();
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');

        $invitation->forceFill(['cancelled' => false, 'token_hash' => null])->save();
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');

        $invitation->forceFill(['token_hash' => Invitation::hashToken($invitation->surveyToken())])->save();
        expect(singleLinkHomeSections($this->token))->toHaveKey('engagement');

        $survey->update(['closes_on' => now()->subDay()->toDateString()]);
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');

        $survey->update(['closes_on' => now()->addDay()->toDateString(), 'status' => 'draft']);
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');

        $survey->update(['status' => 'active']);
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        expect(singleLinkHomeSections($this->token))->not->toHaveKey('engagement');
    });

    it('never offers the survey of somebody else', function (): void {
        $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);
        $beto = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
        $theirs = singleLinkClimateInvitation($beto, $survey);

        $this->get(route('portal.show', $this->token))->assertInertia(function (Assert $page) use ($theirs): void {
            expect(json_encode($page->toArray()['props'], JSON_THROW_ON_ERROR))->not->toContain($theirs->surveyToken());
        });
    });
});
