<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Exceptions\WhatsAppQuotaExceeded;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\RemindPending;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Messaging\Contracts\InvitationLinks;
use App\Modules\Messaging\Data\InvitationReference;
use App\Modules\Messaging\Support\TemplateRegistry;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Notification::fake();
    RateLimiter::clear('survey-invitations');
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 100]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']));
    $this->user = User::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);
});

/** A survey of `$people` people, each with their own phone and email, launched; returns [survey, people]. */
function waSurveyLaunch(Tenant $tenant, WorkCenter $plant, Area $area, User $user, int $people, ?Closure $prepare = null): array
{
    $staff = Employee::factory()->count($people)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id])->values();
    $staff->each(fn (Employee $employee, int $i) => $employee->update(['mobile' => '+52818687'.str_pad((string) (5700 + $i), 4, '0', STR_PAD_LEFT), 'email' => "persona{$i}@example.test"]));
    $prepare !== null && $prepare($staff);
    $survey = Survey::factory()->create(['tenant_id' => $tenant->id]);
    Bus::fake();
    resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $user->id);

    return [$survey->refresh(), $staff];
}

function waSurveyJobs(): array
{
    $jobs = [];
    Bus::assertBatched(function (PendingBatch $batch) use (&$jobs): bool {
        $jobs = $batch->jobs->all();

        return true;
    });

    return $jobs;
}

/** @return list<string> the channel of each invitation sent */
function surveyChannelsSent(): array
{
    $sent = [];
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels) use (&$sent): bool {
        $sent[] = implode(',', $channels);

        return true;
    });

    return $sent;
}

it('reserves all the WhatsApp messages of a launch or none (CUJ 2)', function (): void {
    config(['billing.versions.v2.plans.cumplimiento.quotas.whatsapp' => 5]);

    expect(fn () => waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 6))->toThrow(WhatsAppQuotaExceeded::class);

    expect(Survey::query()->sole()->status)->toBe(SurveyStatus::Draft)
        ->and(Invitation::query()->count())->toBe(0)
        ->and(UsageCounter::query()->where('meter', 'whatsapp')->sum('reserved'))->toBe(0);
});

it('writes to each person through one channel only and keeps it in invited_via', function (): void {
    [$survey, $staff] = waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 7, function ($staff): void {
        $staff[4]->update(['mobile' => null]);
        $staff[5]->update(['mobile' => null, 'email' => null]);
    });
    expect(UsageCounter::query()->where('meter', 'whatsapp')->sole()->reserved)->toBe(5);
    foreach (waSurveyJobs() as $job) {
        $job->handle();
    }

    $invitations = Invitation::query()->where('survey_id', $survey->id)->get()->keyBy('employee_id');
    expect($invitations[$staff[0]->id]->invited_via)->toBe('whatsapp')
        ->and($invitations[$staff[4]->id]->invited_via)->toBe('mail')
        ->and($invitations[$staff[5]->id]->invited_via)->toBeNull()
        ->and($invitations[$staff[5]->id]->invitation_status)->toBe(InvitationStatus::Unreachable);
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 6);
    expect(array_count_values(surveyChannelsSent()))->toEqual(['whatsapp' => 5, 'mail' => 1]);
});

it('never sends a reminder through WhatsApp, and leaves invited_via as the invitation went out', function (): void {
    [$survey, $staff] = waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 6, fn ($staff) => $staff[5]->update(['email' => null]));
    foreach (waSurveyJobs() as $job) {
        $job->handle();
    }
    Notification::fake();
    Bus::fake();

    resolve(RemindPending::class)->handle($survey->id);
    foreach (waSurveyJobs() as $job) {
        $job->handle();
    }

    $invitations = Invitation::query()->where('survey_id', $survey->id)->get()->keyBy('employee_id');
    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 5);
    expect(array_values(array_unique(surveyChannelsSent())))->toBe(['mail'])
        ->and($invitations[$staff[0]->id]->invited_via)->toBe('whatsapp')
        ->and($invitations[$staff[5]->id]->invitation_status)->toBe(InvitationStatus::Sent);
});

it('keeps everything on mail while the function is off, whatever the company rows say', function (): void {
    config(['whatsapp.enabled' => false]);

    [$survey] = waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 6);
    foreach (waSurveyJobs() as $job) {
        $job->handle();
    }

    expect(Invitation::query()->where('survey_id', $survey->id)->pluck('invited_via')->unique()->all())->toBe(['mail'])
        ->and(UsageCounter::query()->where('meter', 'whatsapp')->count())->toBe(0)
        ->and(array_values(array_unique(surveyChannelsSent())))->toBe(['mail']);
});

it('names its invitation so the delivery can be followed', function (): void {
    [$survey] = waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 6);
    foreach (waSurveyJobs() as $job) {
        $job->handle();
    }
    $ids = Invitation::query()->where('survey_id', $survey->id)->pluck('id')->all();

    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable) use ($ids, $survey): bool {
        $reference = $mail->deliveryReference();

        return $reference !== null && $reference->type === InvitationReference::ENGAGEMENT_INVITATION && in_array($reference->id, $ids, true)
            && $reference->sourceType === InvitationReference::ENGAGEMENT_SURVEY && $reference->sourceId === $survey->id;
    });
});

it('tells Messaging, when it runs, the phone, the template, the two parameters and the link, and nothing once the invitation no longer waits', function (): void {
    [$survey] = waSurveyLaunch($this->tenant, $this->plant, $this->area, $this->user, 6);
    $invitation = Invitation::query()->where('survey_id', $survey->id)->firstOrFail();
    $links = collect(app()->tagged(InvitationLinks::TAG))->first(fn (InvitationLinks $links): bool => $links->type() === InvitationReference::ENGAGEMENT_INVITATION);
    $mobile = Employee::query()->findOrFail($invitation->employee_id)->mobile;

    $answer = $links->whatsApp($invitation->id);

    expect($answer?->mobile)->toBe($mobile)
        ->and($answer->template)->toBe('engagement_invite_v1')
        ->and($answer->bodyParameters)->toBe(['Autopartes del Bajío', '20 de noviembre'])
        ->and($answer->buttonSuffix)->toBe($invitation->surveyToken())
        ->and(count($answer->bodyParameters))->toBe((new TemplateRegistry)->get($answer->template)->bodyParameterCount);

    $invitation->update(['answered' => true]);
    expect($links->whatsApp($invitation->id))->toBeNull();
    $invitation->update(['answered' => false, 'cancelled' => true]);
    expect($links->whatsApp($invitation->id))->toBeNull();
    $invitation->update(['cancelled' => false]);
    expect($links->whatsApp($invitation->id))->not->toBeNull();
    $survey->update(['status' => SurveyStatus::Closed, 'closed_at' => now()]);
    expect($links->whatsApp($invitation->id))->toBeNull()
        ->and($links->whatsApp((string) Illuminate\Support\Str::uuid()))->toBeNull();
});
