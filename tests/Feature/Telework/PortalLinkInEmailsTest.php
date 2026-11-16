<?php

declare(strict_types=1);

use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Notifications\SurveyInvitation as EvaluationInvitation;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation as ClimateInvitation;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Support\TokenPortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

mutates(EvaluationInvitation::class, ClimateInvitation::class, SendSurveyInvitations::class, SendInvitations::class, TokenPortalAccess::class);

/*
 * The foot of the survey emails is the person's entrance now (InvitationsTest, SendSurveyInvitationsTest); what is left
 * here goes with the portal link (step 9 of the WorkerAccess design).
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Notification::fake();
    RateLimiter::clear('survey-invitations');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío', 'slug' => 'bajio']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** The HTML of the email `$notification` sent to the address `$email`. */
function sentEmailHtml(string $notification, string $email): string
{
    $html = '';
    Notification::assertSentOnDemand($notification, function (BaseNotification $sent, array $channels, AnonymousNotifiable $notifiable) use (&$html, $email): bool {
        if ($notifiable->routes['mail'] === $email) {
            $html = (string) $sent->toMail($notifiable)->render();
        }

        return true;
    });

    return $html;
}

it('leaves the foot alone when the email was built without a portal link', function (): void {
    $notification = new ClimateInvitation(false, 'token', 'Ana', resolve(Companies::class)->current(), null, new CarbonImmutable('2026-11-20'), SurveyKind::Climate);

    expect((string) $notification->toMail(new AnonymousNotifiable)->render())->not->toContain('Todo lo suyo');
});

it('answers null and logs the class of the failure, never the message, when the portal cannot be issued', function (): void {
    $ana = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    Log::spy();
    DB::shouldReceive('transaction')->andThrow(new RuntimeException('connection to 10.0.0.9 lost'));

    expect(resolve(PortalAccess::class)->url($ana->id))->toBeNull();
    Log::shouldHaveReceived('warning')->once()->with('telework.portal_url_unavailable', ['employee_id' => $ana->id, 'exception' => RuntimeException::class]);
});
