<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Actions\LaunchSeatingSurvey;
use App\Modules\Campaigns\Actions\SendProgressDigests;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Seating\Http\Controllers\SurveyController;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(LaunchSeatingSurvey::class, SurveyController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->people = staffOf($this->tenant, $this->plant, 4)->values();
});

function launchSurvey(?WorkCenter $center = null, array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.seating.survey.launch', [test()->tenant, ($center ?? test()->plant)->id]), ['closes_on' => '2026-11-12', ...$overrides]);
}

it('launches for every active person of the center, with no sample, and invites those with an email while the rest wait', function (): void {
    Bus::fake();
    $this->people[0]->update(['deactivated_at' => now()]);
    $this->people[1]->update(['email' => null]);
    staffOf($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), 3);
    $this->actingAs($this->hr);

    launchSurvey()->assertRedirect()->assertSessionHasNoErrors();

    $campaign = Campaign::query()->sole();
    expect($campaign->kind)->toBe(CampaignKind::SeatingSurvey)->and($campaign->status->value)->toBe('active')->and($campaign->instruments)->toBe(['seating.survey'])
        ->and($campaign->work_center_id)->toBe($this->plant->id)->and($campaign->population)->toBeNull()->and($campaign->closes_on?->toDateString())->toBe('2026-11-12')
        ->and(Participant::query()->where('campaign_id', $campaign->id)->count())->toBe(3)
        ->and(ParticipantInstrument::query()->where('instrument', 'seating.survey')->where('status', 'pending')->count())->toBe(3);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1 && $batch->jobs->first() instanceof SendSurveyInvitations);
    expect(Participant::query()->where('invitation_status', 'queued')->count())->toBe(2)->and(Participant::query()->where('invitation_status', 'no_email')->count())->toBe(1);
});

it('writes the launch to the audit trail with counts only', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);

    launchSurvey();

    $entry = AuditEntry::query()->where('event', 'campaign.launched')->sole();
    expect($entry->metadata)->toMatchArray(['participants' => 4, 'instruments' => ['seating.survey']])->and(json_encode($entry->metadata))->not->toContain($this->people[0]->name);
});

it('lets only manage-seating launch it', function (TenantRole $role): void {
    Bus::fake();
    $this->actingAs(memberOf($this->tenant, $role));

    launchSurvey()->assertForbidden();

    expect(Campaign::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('refuses a deadline that is not tomorrow or later, another center, an archived one and a second open survey', function (): void {
    Bus::fake();
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
    $this->actingAs($this->hr);

    launchSurvey(null, ['closes_on' => '2026-11-02'])->assertSessionHasErrors('closes_on');
    launchSurvey($archived)->assertNotFound();
    launchSurvey()->assertSessionHasNoErrors();
    launchSurvey()->assertSessionHasErrors('work_center_id');

    expect(Campaign::query()->count())->toBe(1)
        ->and(fn () => DB::transaction(fn () => Campaign::factory()->active('2026-11-20')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'kind' => CampaignKind::SeatingSurvey])))
        ->toThrow(QueryException::class, 'campaigns_one_open_seating_survey');
});

it('lets the center be surveyed again once the survey closed', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    Campaign::query()->update(['status' => 'closed', 'closed_at' => now()]);

    launchSurvey()->assertSessionHasNoErrors();

    expect(Campaign::query()->count())->toBe(2);
});

it('accepts one submission per link and rejects the second', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    $participant = Participant::query()->firstOrFail();
    $participant->forceFill(['token_hash' => Participant::hashToken('the-token')])->save();

    $first = $this->post(route('survey.submit', ['the-token', 'seating.survey']), ['answers' => surveyAnswers()]);
    $second = $this->post(route('survey.submit', ['the-token', 'seating.survey']), ['answers' => surveyAnswers('no')]);

    $first->assertRedirect(route('survey.show', 'the-token'));
    expect(App\Modules\Responses\Models\AnonymousResponse::query()->count())->toBe(1)->and(App\Modules\Responses\Models\AnonymousResponse::query()->sole()->answers['1'])->toBe('yes');
    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->component('campaigns/survey')->where('unavailable', 'done'));
    $second->assertRedirect();
});

it('shows the person the survey with its gate, as any other survey', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    Participant::query()->firstOrFail()->forceFill(['token_hash' => Participant::hashToken('the-token')])->save();

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->component('campaigns/survey')->where('instruments.0.key', 'seating.survey')
        ->where('instruments.0.identified', false)->where('instruments.0.sections.0.gate.key', 'stands')->has('instruments.0.sections.0.items', 8));
});

it('tells the survey page its kind, so Ley Silla gets its own cover and NOM-035 keeps its own', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    Participant::query()->firstOrFail()->forceFill(['token_hash' => Participant::hashToken('the-token')])->save();

    $this->get(route('survey.show', 'the-token'))->assertInertia(fn (Assert $page) => $page->where('kind', 'seating_survey'));
});

it('keeps the NOM-035 pages, counts, records and digests unchanged with a seating survey open', function (): void {
    Bus::fake();
    Notification::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    $survey = Campaign::query()->sole();
    Campaign::query()->whereKey($survey->id)->update(['launched_at' => now()]);

    expect(resolve(OpenCampaigns::class)->inCourse()->count)->toBe(0)
        ->and(resolve(CampaignRecords::class)->find($survey->id))->toBeNull()
        ->and(resolve(CampaignRecords::class)->current())->toBe([]);
    $this->get(route('tenant.campaigns.show', [$this->tenant, $survey->id]))->assertNotFound();
    $this->get(route('tenant.campaigns.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('closed.total', 0)->where('events.total', 0));
    expect(resolve(SendProgressDigests::class)->handle())->toBe(0);
    Notification::assertNothingSent();
});

it('does not list a closed seating survey among the closed NOM-035 campaigns', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();
    Campaign::query()->update(['status' => 'closed', 'closed_at' => now()]);

    $this->get(route('tenant.campaigns.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('closed.total', 0));
});

it('sends the invitation as Ley Silla, with its own words, not as the NOM-035 evaluation', function (): void {
    Bus::fake();
    Notification::fake();
    $this->actingAs($this->hr);
    $jobs = [];
    launchSurvey();
    Bus::assertBatched(function (PendingBatch $batch) use (&$jobs): bool {
        $jobs = $batch->jobs->all();

        return true;
    });

    foreach ($jobs as $job) {
        $job->handle();
    }

    $rendered = '';
    Notification::assertSentOnDemand(App\Modules\Campaigns\Notifications\SurveyInvitation::class, function ($notification, array $channels, $notifiable) use (&$rendered): bool {
        $rendered = (string) $notification->toMail($notifiable)->render();

        return true;
    });
    expect($rendered)->toContain('Ley Silla')->and($rendered)->not->toContain('NOM-035')->and($rendered)->toContain('anónim');
});

it('lists the surveys of a center through the contract, newest first', function (): void {
    Bus::fake();
    $this->actingAs($this->hr);
    launchSurvey();

    $surveys = resolve(CenterSurveys::class)->forCenter($this->plant->id);

    expect($surveys)->toHaveCount(1)->and($surveys[0]->status)->toBe('active')->and($surveys[0]->participants)->toBe(4)->and($surveys[0]->closesOn)->toBe('2026-11-12');
});
