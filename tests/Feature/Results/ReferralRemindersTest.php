<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Actions\RemindReferrals;
use App\Modules\Results\Jobs\SendReferralReminder;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Notifications\ReferralFollowUp;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(RemindReferrals::class, SendReferralReminder::class, ReferralFollowUp::class);

// Rediseño v2 (board V4-N035-Canalizacion): «Recordarme en 30 días preguntar cómo sigue» reaches whoever recorded it, once,
// on its day, without the name, the destination or the note.

beforeEach(function (): void {
    Carbon::setTestNow('2026-12-04 14:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
});

function referralDue(string $remindOn, array $state = []): ScreeningReferral
{
    $screening = TraumaScreening::factory()->create(['campaign_id' => test()->campaign->id, 'requires_clinical_assessment' => true]);

    return ScreeningReferral::factory()->create(['tenant_id' => test()->tenant->id, 'campaign_id' => test()->campaign->id, 'employee_id' => $screening->employee_id, 'updated_by' => test()->hr->id, 'remind_on' => $remindOn, 'note' => 'Nota privada', ...$state]);
}

it('reminds whoever recorded it on its day, once, with no name nor what was recorded', function (): void {
    $due = referralDue('2026-12-04');
    referralDue('2026-12-05');
    referralDue('2026-12-01', ['remind_on' => null]);

    expect(resolve(RemindReferrals::class)->handle())->toBe(1)
        ->and(resolve(RemindReferrals::class)->handle())->toBe(0)
        ->and($due->refresh()->reminded_at)->not->toBeNull();

    Notification::assertSentOnDemandTimes(ReferralFollowUp::class, 1);
    Notification::assertSentOnDemand(ReferralFollowUp::class, function (ReferralFollowUp $notification, array $channels, AnonymousNotifiable $notifiable) use ($due): bool {
        $mail = $notification->toMail($notifiable);
        $text = implode(' ', [$mail->subject, ...$mail->introLines]);

        return $notifiable->routes['mail'] === $this->hr->email
            && $mail->actionUrl === route('tenant.campaigns.screenings', ['acme', $this->campaign->id, 'view' => 'channeled', 'person' => $due->employee_id])
            && ! str_contains($text, 'IMSS') && ! str_contains($text, 'Nota privada');
    });
});

it('reminds nobody who may no longer see the Guía I', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);
    referralDue('2026-12-04', ['updated_by' => $viewer->id]);

    expect(resolve(RemindReferrals::class)->handle())->toBe(1);
    Notification::assertNothingSent();
});

it('logs how many it sent, and nothing about anyone', function (): void {
    Log::spy();
    referralDue('2026-12-04');

    resolve(RemindReferrals::class)->handle();

    Log::shouldHaveReceived('info')->with('results.referral_reminders_sent', ['sent' => 1])->once();
});
