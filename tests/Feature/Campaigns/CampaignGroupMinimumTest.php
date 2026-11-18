<?php

declare(strict_types=1);

use App\Modules\Campaigns\Actions\LaunchCampaign;
use App\Modules\Campaigns\Actions\LaunchSeatingSurvey;
use App\Modules\Campaigns\Actions\LaunchTraumaCampaign;
use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Campaigns\Support\CampaignPeople;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Campaign::class, LaunchCampaign::class, LaunchSeatingSurvey::class, LaunchTraumaCampaign::class, SurveyInvitation::class);

// The minimum of people per group is the company's (1 to 50), and each campaign keeps the one it had when it launched:
// the invitation promised that number to whoever answers, so a later change of the company's never reaches it.

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** The company now asks for `$minimum` people per group, as the next request reads it. */
function setCompanyMinimum(Tenant $tenant, ?int $minimum): void
{
    $tenant->forceFill(['min_group' => $minimum])->save();
    app()->forgetScopedInstances();
}

/** Launches an evaluation of 20 people of the plant, its links still in the queue. */
function evaluationLaunchedWithMinimum(mixed $test): Campaign
{
    staffOf($test->tenant, $test->plant, 20)->first()?->update(['name' => 'Aaron Pérez']);
    $test->actingAs($test->hr)->post(route('tenant.campaigns.store', $test->tenant), ['work_center_id' => $test->plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Bus::fake();
    $test->actingAs($test->hr)->post(route('tenant.campaigns.launch', [$test->tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();

    return $campaign->fresh() ?? throw new RuntimeException('The campaign is gone.');
}

/** Sends the links the launch queued and gives the email Aaron Pérez got. */
function frozenInvitationOfAaron(): string
{
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $batch->jobs->each(fn (SendSurveyInvitations $job) => $job->handle());

        return true;
    });
    $email = Employee::query()->withoutGlobalScopes()->where('name', 'Aaron Pérez')->sole()->email;
    $html = null;
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$html): bool {
        if ($notifiable->routes['mail'] === $email) {
            $html = (string) $notification->toMail($notifiable)->render();
        }

        return true;
    });

    return $html ?? throw new RuntimeException('Aaron got no email.');
}

it('freezes the company minimum when an evaluation launches, and a later change does not reach it', function (int $minimum): void {
    setCompanyMinimum($this->tenant, $minimum);

    $campaign = evaluationLaunchedWithMinimum($this);
    setCompanyMinimum($this->tenant, 7);
    workIn($this->tenant);

    expect($campaign->min_group)->toBe($minimum)
        ->and($campaign->groupMinimum())->toBe($minimum);
})->with([3, 1, 12]);

it('freezes the platform default for a company that never chose one', function (): void {
    config(['results.min_group' => 6]);

    expect(evaluationLaunchedWithMinimum($this)->min_group)->toBe(6);
});

it('lets a draft follow the company minimum until it launches', function (): void {
    workIn($this->tenant);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    setCompanyMinimum($this->tenant, 3);
    expect($draft->min_group)->toBeNull()->and($draft->groupMinimum())->toBe(3);

    setCompanyMinimum($this->tenant, 12);
    expect($draft->groupMinimum())->toBe(12);
});

it('freezes the minimum of each company apart', function (): void {
    $other = Tenant::factory()->create(['min_group' => 9]);
    setCompanyMinimum($this->tenant, 3);

    $campaign = evaluationLaunchedWithMinimum($this);

    expect($campaign->min_group)->toBe(3)
        ->and($other->fresh()?->min_group)->toBe(9);
});

it('freezes the minimum of a Ley Silla survey and of a Guía I by event when they launch', function (): void {
    $people = staffOf($this->tenant, $this->plant, 2);
    setCompanyMinimum($this->tenant, 4);
    Bus::fake();

    $this->actingAs($this->hr)->post(route('tenant.seating.survey.launch', [$this->tenant, $this->plant->id]), ['closes_on' => '2026-11-12'])->assertSessionHasNoErrors();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.trauma.store', $this->tenant), ['work_center_id' => $this->plant->id, 'employee_ids' => [$people[0]->id], 'closes_on' => '2026-11-09'])->assertSessionHasNoErrors();

    expect(Campaign::query()->withoutGlobalScopes()->pluck('min_group')->all())->toBe([4, 4]);
});

it('promises in the invitation the minimum frozen at launch, even when the company changes it before the email goes', function (): void {
    setCompanyMinimum($this->tenant, 12);
    evaluationLaunchedWithMinimum($this);
    setCompanyMinimum($this->tenant, 3);

    expect(frozenInvitationOfAaron())->toContain('Recursos Humanos solo recibe resultados de grupos de 12 o más personas.');
});

it('promises no hiding in the invitation when the company set no minimum', function (): void {
    setCompanyMinimum($this->tenant, 1);
    evaluationLaunchedWithMinimum($this);

    expect(frozenInvitationOfAaron())
        ->toContain(e(trans('group-minimum.notice_none')))
        ->not->toContain('grupos de 1 o más');
});

it('counts the answers by day only from the minimum frozen at launch', function (): void {
    setCompanyMinimum($this->tenant, 3);
    $campaign = evaluationLaunchedWithMinimum($this);
    setCompanyMinimum($this->tenant, 12);
    workIn($this->tenant);

    expect(resolve(CampaignPeople::class)->answersByDay($campaign)['minimum'])->toBe(3);
});

it('offers the company minimum of now to the evaluations still to launch', function (): void {
    setCompanyMinimum($this->tenant, 3);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->component('campaigns/index')->where('minGroup', 3));
});
