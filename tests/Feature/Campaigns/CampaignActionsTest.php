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
use Inertia\Testing\AssertableInertia as Assert;

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

it('sends the link again to everyone with a guide pending and an email, the same link they already had', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $done = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->first();
    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $done?->id)->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    $before = Participant::query()->withoutGlobalScopes()->where('id', '!=', $done?->id)->pluck('token_hash', 'id');

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Enlace reenviado a 19 personas.']]);

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 19);
    $after = Participant::query()->withoutGlobalScopes()->where('id', '!=', $done?->id)->pluck('token_hash', 'id');

    // Someone halfway through the survey keeps going on the link they opened.
    expect($after->all())->toBe($before->all());
});

it('keeps the link someone already opened working after a reminder, and still single-use once they answer', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: fn () => staffOf($this->tenant, $this->plant, 10));
    $participant = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->firstOrFail();
    $links = [];
    $this->actingAs($this->hr)->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]));
    $this->actingAs($this->hr)->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]));
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $notification, array $channels, Illuminate\Notifications\AnonymousNotifiable $notifiable) use (&$links): bool {
        $links[] = (string) $notification->toMail($notifiable)->actionUrl;

        return true;
    });
    $mine = array_values(array_filter($links, fn (string $link): bool => Participant::query()->withoutGlobalScopes()->where('token_hash', Participant::hashToken(basename($link)))->value('id') === $participant->id));

    expect($mine)->toHaveCount(2)->and($mine[0])->toBe($mine[1]);
    $this->get($mine[0])->assertInertia(fn ($page) => $page->where('unavailable', null));

    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $participant->id)->update(['status' => InstrumentStatus::Completed, 'completed_at' => now()]);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.resend', [$this->tenant, $campaign->id]));

    $this->get($mine[0])->assertInertia(fn ($page) => $page->where('unavailable', 'done'));
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

it('keeps the link of someone who returned without an email: it works again, so they are not «Sin correo»', function (): void {
    $people = staffOf($this->tenant, $this->plant, 18)->values();
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: fn () => null);
    $returned = participantOf($campaign, $people[1]);
    $link = $returned->token_hash;
    ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', $returned->id)->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);
    $people[1]->update(['email' => null, 'mobile' => '5512345678']);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se puso al día a 1 persona, sin enviar enlace: no tiene correo.']]);

    $returned = participantOf($campaign, $people[1]);
    expect(guidesOf($returned))->toBe(['nom035.guide-i' => 'pending', 'nom035.guide-ii' => 'pending'])
        ->and($returned->token_hash)->toBe($link)
        ->and($returned->invitation_status)->toBe(App\Modules\Campaigns\Enums\InvitationStatus::Sent);
    Notification::assertNothingSent();
});

it('says who was brought up to date and who of them got a link, when someone has no email', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    staffOf($this->tenant, $this->plant, 2);
    staffOf($this->tenant, $this->plant, 1)->first()?->update(['email' => null, 'mobile' => '5512345678']);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Se puso al día a 3 personas y se envió enlace a 2.']]);
});

it('says when there is nobody to invite', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.invite-missing', [$this->tenant, $campaign->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'info', 'message' => 'No falta nadie por invitar.']]);
});

it('tells the page how many people inviting the missing brings up to date and how many get a link, exactly as it then does', function (Closure $change, int $updated, int $links): void {
    $people = staffOf($this->tenant, $this->plant, 18)->values();
    $people[0]->update(['email' => null, 'mobile' => '5512345678']);
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: fn () => null);
    $change->call($this, $campaign, $people);
    $show = fn () => $this->actingAs($this->hr)->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]));

    $show()->assertInertia(fn (Assert $page) => $page->where('missing', ['people' => $updated, 'links' => $links]));

    expect(resolve(InviteMissing::class)->handle($campaign))->toBe(['people' => $updated, 'links' => $links]);
    $show()->assertInertia(fn (Assert $page) => $page->where('missing', ['people' => 0, 'links' => 0]));
})->with([
    'nobody' => [fn (Campaign $campaign) => null, 0, 0],
    'hired, with an email' => [fn (Campaign $campaign) => staffOf($this->tenant, $this->plant, 2), 2, 2],
    'hired, without one' => [fn (Campaign $campaign) => staffOf($this->tenant, $this->plant, 1)->first()?->update(['email' => null, 'mobile' => '5512345678']), 1, 0],
    'got an email after the launch' => [fn (Campaign $campaign, $people) => $people[0]->update(['email' => 'ana@acme.mx']), 1, 1],
    'returned, with an email' => [fn (Campaign $campaign, $people) => ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', participantOf($campaign, $people[1])->id)->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]), 1, 1],
    'returned, with a link but no email now' => [function (Campaign $campaign, $people): void {
        ParticipantInstrument::query()->withoutGlobalScopes()->where('participant_id', participantOf($campaign, $people[1])->id)->update(['status' => InstrumentStatus::Cancelled, 'cancelled_at' => now()]);
        $people[1]->update(['email' => null, 'mobile' => '5512345678']);
    }, 1, 0],
]);

it('gives the count only to who may invite, while an evaluation runs', function (): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr);
    $trauma = Campaign::factory()->trauma()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))->assertInertia(fn (Assert $page) => $page->where('missing', null));

    foreach ([$trauma, $closed] as $other) {
        $this->actingAs($this->hr)->get(route('tenant.campaigns.show', [$this->tenant, $other->id]))->assertInertia(fn (Assert $page) => $page->where('missing', null));
    }
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

it('does nothing to an evaluation another request closed meanwhile', function (string $action): void {
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, 'sample', fn () => staffOf($this->tenant, $this->plant, female: 40, male: 20));
    staffOf($this->tenant, $this->plant, female: 1);
    $stale = Campaign::query()->findOrFail($campaign->id);
    Campaign::query()->whereKey($campaign->id)->update(['status' => App\Modules\Campaigns\Enums\CampaignStatus::Closed, 'closed_at' => now()]);
    $participants = Participant::query()->withoutGlobalScopes()->count();

    expect(fn () => match ($action) {
        'invite missing' => resolve(InviteMissing::class)->handle($stale),
        'replacements' => resolve(DrawReplacements::class)->handle($stale, 'female', 1),
        'resend' => resolve(ResendInvitations::class)->handle($stale),
    })->toThrow(App\Modules\Campaigns\Exceptions\CampaignStateChanged::class);

    Notification::assertNothingSent();
    expect(Participant::query()->withoutGlobalScopes()->count())->toBe($participants);
})->with(['invite missing', 'replacements', 'resend']);

it('resends to any number of people, past what Postgres binds one by one', function (): void {
    $people = staffOf($this->tenant, $this->plant, 18)->values();
    $campaign = launchedEvaluation($this->tenant, $this->plant, $this->hr, staff: fn () => null);
    $ids = [...array_map(fn (): string => (string) Illuminate\Support\Str::uuid(), range(1, 70_000)), $people[0]->id];

    expect(resolve(ResendInvitations::class)->handle($campaign, $ids))->toBe(1);
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 1);
});
