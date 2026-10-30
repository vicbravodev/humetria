<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\DrawReplacements;
use App\Modules\Campaigns\Actions\InviteMissing;
use App\Modules\Campaigns\Actions\ResendInvitations;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Http\Controllers\CampaignActionController;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;

mutates(ResendInvitations::class, InviteMissing::class, DrawReplacements::class, CampaignActionController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
});

/** A launched evaluation of the plant with `$people` (or the given) people, with the notifications of the launch already gone. */
function launchedEvaluation(Tenant $tenant, WorkCenter $plant, App\Models\User $hr, string $population = 'all', ?Closure $staff = null): Campaign
{
    $staff === null ? staffOf($tenant, $plant, 20) : $staff();
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id, 'population' => $population]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Notification::fake();
    test()->actingAs($hr)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();
    Notification::fake();

    return $campaign;
}

function participantOf(Campaign $campaign, Employee $employee): Participant
{
    return Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->where('employee_id', $employee->id)->sole();
}

/** @return array<string, string> the person's guides, by status */
function guidesOf(Participant $participant): array
{
    return ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $participant->id)->orderBy('instrument')->get()
        ->mapWithKeys(fn (ParticipantInstrument $guide): array => [$guide->instrument => $guide->status->value])->all();
}

it('sends the link again to everyone with a guide pending and an email, killing their old link', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $done = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->first();
    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $done?->id)->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    $before = Participant::query()->withoutGlobalScopes()->where('id', '!=', $done?->id)->pluck('token_hash', 'id');

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace reenviado a 19 personas.']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 19);
    $after = Participant::query()->withoutGlobalScopes()->where('id', '!=', $done?->id)->pluck('token_hash', 'id');

    foreach ($after as $id => $hash) {
        expect($hash)->not->toBe($before[$id]);
    }
});

it('sends the link again only to the pending people the filters narrow the list to', function (): void {
    $people = collect();
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: function () use (&$people): void {
        $people = staffOf($this->tenant, $this->plant, 20)->values();
        $people[0]->update(['name' => 'Zyx López']);
        $people[1]->update(['name' => 'Zyx María Ruiz']);
        $people[2]->update(['name' => 'Zyxa Soto']);
    });
    $answered = participantOf($campaign, $people[2]);
    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $answered->id)->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    $untouched = participantOf($campaign, $people[3])->token_hash;

    $this->actingAs($this->hr)
        // Whatever standing the list showed, only pending people get a link.
        ->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]), ['q' => 'zyx', 'status' => 'all', 'guide' => 'nom035.guide-ii'])
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace reenviado a 2 personas.']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 2);
    expect(participantOf($campaign, $people[3])->token_hash)->toBe($untouched)
        ->and(participantOf($campaign, $people[0])->token_hash)->not->toBe($untouched);
});

it('sends the link again to whoever it failed to reach, and no one else, when the list shows them', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $failed = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->limit(3)->pluck('id');
    Participant::query()->withoutGlobalScopes()->whereKey($failed)->update(['invitation_status' => App\Modules\Campaigns\Enums\InvitationStatus::Failed]);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]), ['status' => 'failed'])
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace reenviado a 3 personas.']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 3);
    expect(Participant::query()->withoutGlobalScopes()->whereKey($failed)->pluck('invitation_status')->unique()->all())->toBe([App\Modules\Campaigns\Enums\InvitationStatus::Sent]);
});

it('sends the link again to one person', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->first();

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.participants.resend', [$this->tenant, $campaign->id, $participant?->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace reenviado.']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 1);
});

it('cannot resend to someone with nothing pending or no email', function (string $case): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->firstOrFail();
    $case === 'done'
        ? ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $participant->id)->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()])
        : Employee::query()->withoutGlobalScopes()->whereKey($participant->employee_id)->update(['email' => null, 'mobile' => '5512345678']);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.participants.resend', [$this->tenant, $campaign->id, $participant->id]))->assertStatus(409);

    Notification::assertNothingSent();
})->with(['done', 'no email']);

it('invites people hired after the launch: Guía I and the II', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $newcomer = staffOf($this->tenant, $this->plant, 1)->first();

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se invitó a 1 persona.']]);

    expect(guidesOf(participantOf($campaign, $newcomer)))->toBe(['nom035.guide-i' => 'pending', 'nom035.guide-ii' => 'pending']);
    Notification::assertSentOnDemand(SurveyInvitation::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === $newcomer->email);
});

it('gives a newcomer Guía III when everyone takes it, never when a sample does', function (string $population, array $guides): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, $population, fn () => staffOf($this->tenant, $this->plant, 60));
    $newcomer = staffOf($this->tenant, $this->plant, 1)->first();

    $this->actingAs($this->hr)->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]));

    expect(guidesOf(participantOf($campaign, $newcomer)))->toBe($guides);
})->with([
    'everyone' => ['all', ['nom035.guide-i' => 'pending', 'nom035.guide-iii' => 'pending']],
    'a sample' => ['sample', ['nom035.guide-i' => 'pending']],
]);

it('invites someone who got an email after the launch, and gives back the guides of someone who returned', function (): void {
    $people = staffOf($this->tenant, $this->plant, 18)->values();
    $people[0]->update(['email' => null, 'mobile' => '5512345678']);
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: fn () => null);
    $returned = participantOf($campaign, $people[1]);
    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $returned->id)->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);
    $people[0]->update(['email' => 'ana@acme.mx']);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se invitó a 2 personas.']]);

    expect(guidesOf($returned))->toBe(['nom035.guide-i' => 'pending', 'nom035.guide-ii' => 'pending'])
        ->and(participantOf($campaign, $people[0])->token_hash)->not->toBeNull();
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 2);
});

it('says when there is nobody to invite', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'info', 'message' => 'No falta nadie por invitar.']]);
});

it('draws replacements of the gender of who will not answer, from people outside the sample', function (string $gender): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, 'sample', fn () => staffOf($this->tenant, $this->plant, female: 40, male: 20));
    $before = Participant::query()->withoutGlobalScopes()->where('in_sample', true)->pluck('employee_id')->all();

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 2, 'gender' => $gender])
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se sortearon 2 personas más para la muestra.']]);

    $drawn = Participant::query()->withoutGlobalScopes()->where('in_sample', true)->whereNotIn('employee_id', $before)->get();
    $genders = Employee::query()->withoutGlobalScopes()->whereIn('id', $drawn->pluck('employee_id'))->pluck('gender')->map->value->unique()->values()->all();

    expect($drawn)->toHaveCount(2)
        ->and($genders)->toBe([$gender])
        ->and($drawn->every(fn (Participant $participant): bool => guidesOf($participant) === ['nom035.guide-i' => 'pending', 'nom035.guide-iii' => 'pending']))->toBeTrue();
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 2);
})->with(['female', 'male']);

it('draws no more replacements than people of that gender outside the sample', function (): void {
    // 40 women and 20 men: the sample of 53 takes 35 and 18, leaving 5 and 2.
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, 'sample', fn () => staffOf($this->tenant, $this->plant, female: 40, male: 20));

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 3, 'gender' => 'male'])
        ->assertSessionHasErrors(['count' => 'Solo quedan 2 personas de ese género fuera de la muestra.']);
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 1, 'gender' => 'other'])
        ->assertSessionHasErrors(['count' => 'Ya no queda nadie de ese género fuera de la muestra.']);
});

it('draws replacements only for a sampled evaluation, a sensible number and a known gender', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);

    $this->actingAs($this->hr)->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 2, 'gender' => 'female'])->assertStatus(409);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 0, 'gender' => 'female'])->assertSessionHasErrors('count');
    $this->actingAs($this->hr)->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 1, 'gender' => 'robot'])->assertSessionHasErrors('gender');
    $this->actingAs($this->hr)->post(route('tenant.campaigns.replacements', [$this->tenant, $campaign->id]), ['count' => 1])->assertSessionHasErrors('gender');
});

it('acts only on an active campaign, for those who manage campaigns, in their own company', function (string $route): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->firstOrFail();
    $parameters = fn (Campaign $target): array => $route === 'tenant.campaigns.participants.resend' ? [$this->tenant, $target->id, $participant->id] : [$this->tenant, $target->id];

    $body = ['count' => 1, 'gender' => 'female'];
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route($route, $parameters($campaign)), $body)->assertForbidden();
    $this->actingAs($this->hr)->post(route($route, $parameters(Campaign::factory()->active()->create())), $body)->assertNotFound();
    Campaign::query()->withoutGlobalScopes()->whereKey($campaign->id)->update(['status' => 'closed', 'closed_at' => now()]);
    $this->actingAs($this->hr)->post(route($route, $parameters($campaign)), $body)->assertStatus(409);
})->with(['tenant.campaigns.resend', 'tenant.campaigns.participants.resend', 'tenant.campaigns.invite-missing', 'tenant.campaigns.replacements']);
