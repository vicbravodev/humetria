<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Actions\LaunchCampaign;
use App\Modules\Campaigns\Actions\SendSurveyInvitation;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Http\Requests\DeadlineRequest;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(LaunchCampaign::class, SendSurveyInvitation::class, DeadlineRequest::class, SurveyInvitation::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
});

function draftFor(Tenant $tenant, WorkCenter $workCenter, App\Models\User $hr, string $population = 'all'): Campaign
{
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $workCenter->id, 'population' => $population]);

    return Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
}

function launch(Tenant $tenant, Campaign $campaign, App\Models\User $user, string $closesOn = '2026-11-20'): Illuminate\Testing\TestResponse
{
    return test()->actingAs($user)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => $closesOn]);
}

/** @return array<string, list<string>> each participant's guides, by employee id */
function guidesByEmployee(Campaign $campaign): array
{
    return Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->with(['instruments' => fn ($query) => $query->withoutGlobalScopes()->orderBy('instrument')])->get()
        ->mapWithKeys(fn (Participant $participant): array => [$participant->employee_id => $participant->instruments->pluck('instrument')->all()])
        ->all();
}

it('launches an evaluation: everyone gets Guía I and the II, and each person with an email gets their link', function (): void {
    $people = staffOf($this->tenant, $this->plant, 20);
    $people->first()->update(['email' => null]);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);
    Log::spy();

    launch($this->tenant, $campaign, $this->hr)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.show', [$this->tenant, $campaign->id]));

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Active)
        ->and($campaign->launched_at?->toIso8601String())->toBe('2026-11-02T17:00:00+00:00')
        ->and($campaign->closes_on?->toDateString())->toBe('2026-11-20')
        ->and(array_values(array_unique(array_map(fn (array $guides): string => implode(',', $guides), guidesByEmployee($campaign)))))->toBe(['nom035.guide-i,nom035.guide-ii'])
        ->and(guidesByEmployee($campaign))->toHaveCount(20)
        ->and(ParticipantInstrument::query()->withoutGlobalScopes()->where('status', '!=', InstrumentStatus::Pending)->count())->toBe(0);

    $invited = Participant::query()->withoutGlobalScopes()->whereNotNull('token_hash')->get();

    expect($invited)->toHaveCount(19)
        ->and($invited->every(fn (Participant $participant): bool => $participant->invited_at !== null))->toBeTrue()
        ->and(Participant::query()->withoutGlobalScopes()->where('employee_id', $people->first()->id)->sole()->token_hash)->toBeNull();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 19);
    Log::shouldHaveReceived('info')->with('campaign.launched', ['campaign_id' => $campaign->id, 'workers' => 20, 'participants' => 20, 'invited' => 19]);
});

it('emails a link that opens that person\'s survey, written as the survey speaks', function (): void {
    $person = staffOf($this->tenant, $this->plant, 16)->first();
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);

    launch($this->tenant, $campaign, $this->hr);

    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($person): bool {
        if ($notifiable->routes['mail'] !== $person->email) {
            return false;
        }

        $mail = $notification->toMail($notifiable);
        $token = basename((string) $mail->actionUrl);

        expect($mail->subject)->toBe('Autopartes del Bajío le invita a contestar una evaluación confidencial')
            ->and($mail->actionText)->toBe('Contestar evaluación')
            ->and(implode(' ', $mail->introLines))->toContain('NOM-035')->toContain('20 nov 2026')
            ->and(Participant::query()->withoutGlobalScopes()->where('token_hash', Participant::hashToken($token))->sole()->employee_id)->toBe($person->id);

        test()->get($mail->actionUrl)->assertOk()->assertInertia(fn ($page) => $page->where('unavailable', null)->where('company', 'Autopartes del Bajío'));

        return true;
    });
});

it('gives Guía III to everyone, or only to the sampled people', function (string $population, int $withThree): void {
    staffOf($this->tenant, $this->plant, female: 40, male: 20);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr, $population);
    $sampled = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('employee_id')->all();

    launch($this->tenant, $campaign, $this->hr)->assertSessionHasNoErrors();

    $guides = guidesByEmployee($campaign);

    expect($guides)->toHaveCount(60)
        ->and(collect($guides)->filter(fn (array $list): bool => in_array('nom035.guide-iii', $list, true))->count())->toBe($withThree)
        ->and(collect($guides)->every(fn (array $list): bool => in_array('nom035.guide-i', $list, true)))->toBeTrue();

    if ($population === 'sample') {
        foreach ($sampled as $employeeId) {
            expect($guides[$employeeId])->toBe(['nom035.guide-i', 'nom035.guide-iii']);
        }
    }
})->with([['all', 60], ['sample', 53]]);

it('records the launch in the audit trail with counts, never names or emails', function (): void {
    staffOf($this->tenant, $this->plant, 16);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);

    launch($this->tenant, $campaign, $this->hr);

    $entry = AuditEntry::query()->where('event', 'campaign.launched')->sole();

    expect($entry->subject_id)->toBe($campaign->id)
        ->and($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->actor_id)->toBe($this->hr->id)
        ->and($entry->metadata)->toEqual(['workers' => 16, 'participants' => 16, 'invited' => 16, 'instruments' => ['nom035.guide-i', 'nom035.guide-ii'], 'population' => 'all', 'sample_size' => null]);
});

it('refuses to launch when the headcount now calls for other guides', function (): void {
    staffOf($this->tenant, $this->plant, 16);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);
    Employee::query()->withoutGlobalScopes()->limit(1)->update(['deactivated_at' => now()]);

    launch($this->tenant, $campaign, $this->hr)
        ->assertSessionHasErrors(['campaign' => 'La plantilla cambió desde que preparaste el borrador: ahora aplican otras guías o otra muestra. Elimina el borrador y prepara uno nuevo.']);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
    Notification::assertNothingSent();
});

it('updates the headcount at launch when it changed but the guides did not', function (): void {
    staffOf($this->tenant, $this->plant, 20);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);
    staffOf($this->tenant, $this->plant, 2);

    launch($this->tenant, $campaign, $this->hr)->assertSessionHasNoErrors();

    expect($campaign->refresh()->workers)->toBe(22)
        ->and(guidesByEmployee($campaign))->toHaveCount(22);
});

it('refuses to launch a sample when someone drawn left or the split by gender changed', function (Closure $change): void {
    staffOf($this->tenant, $this->plant, female: 40, male: 20);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr, 'sample');
    $change($campaign, $this->tenant, $this->plant);

    launch($this->tenant, $campaign, $this->hr)
        ->assertSessionHasErrors(['campaign' => 'La plantilla cambió desde que sorteaste la muestra. Vuelve a sortearla antes de lanzar.']);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
})->with([
    'a sampled person left, another came' => [function (Campaign $campaign, Tenant $tenant, WorkCenter $plant): void {
        $sampled = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->first();
        $gone = Employee::query()->withoutGlobalScopes()->findOrFail($sampled?->employee_id);
        $gone->update(['deactivated_at' => now()]);
        $gender = $gone->gender->value;
        staffOf($tenant, $plant, female: $gender === 'female' ? 1 : 0, male: $gender === 'male' ? 1 : 0, other: $gender === 'other' ? 1 : 0);
    }],
    'one woman less, one man more' => [function (Campaign $campaign, Tenant $tenant, WorkCenter $plant): void {
        $sampled = Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('employee_id')->all();
        Employee::query()->withoutGlobalScopes()->where('gender', 'female')->whereNotIn('id', $sampled)->limit(1)->update(['deactivated_at' => now()]);
        staffOf($tenant, $plant, female: 0, male: 1);
    }],
]);

it('refuses a deadline that is not after today, in Mexico City', function (string $closesOn): void {
    staffOf($this->tenant, $this->plant, 16);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);

    launch($this->tenant, $campaign, $this->hr, $closesOn)->assertSessionHasErrors('closes_on');

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
})->with(['2026-11-02', '2026-11-01', 'mañana', '']);

it('takes tomorrow in Mexico City as the earliest deadline', function (): void {
    // 17:00 UTC is 11:00 in Mexico City: tomorrow there is 3 November.
    staffOf($this->tenant, $this->plant, 16);
    $campaign = draftFor($this->tenant, $this->plant, $this->hr);

    launch($this->tenant, $campaign, $this->hr, '2026-11-03')->assertSessionHasNoErrors();
});

it('launches only a draft, and only for those who manage campaigns', function (): void {
    $active = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    launch($this->tenant, $active, $this->hr)->assertStatus(409);

    staffOf($this->tenant, $this->plant, 16);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);
    launch($this->tenant, $draft, memberOf($this->tenant, TenantRole::Viewer))->assertForbidden();
    launch($this->tenant, Campaign::factory()->create(), $this->hr)->assertNotFound();
});
