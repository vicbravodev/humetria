<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Campaigns\Jobs\SendSurveyInvitations;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Notifications\SurveyInvitation as EvaluationInvitation;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation as ClimateInvitation;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Support\TokenPortalAccess;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

mutates(EvaluationInvitation::class, ClimateInvitation::class, SendSurveyInvitations::class, SendInvitations::class, TokenPortalAccess::class);

/*
 * The email of every survey link a person gets (the evaluation, the climate survey) ends with the one
 * link of their portal, so they keep that one (docs/superpowers/specs/2026-11-14-single-personal-link-design.md).
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

it('adds the portal of the person at the foot of the evaluation email', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $ana = staffOf($this->tenant, $this->plant, 20)->firstOrFail();
    $this->actingAs($hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    Bus::fake();
    $this->actingAs($hr)->post(route('tenant.campaigns.launch', [$this->tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $batch->jobs->each(fn (SendSurveyInvitations $job) => $job->handle());

        return true;
    });

    $html = sentEmailHtml(EvaluationInvitation::class, (string) $ana->email);

    expect($html)->toContain('Todo lo suyo, en un solo lugar')
        ->toContain(route('portal.show', resolve(IssuePortalLink::class)->handle($ana->id)));
});

it('adds the portal of the person at the foot of the climate survey email', function (): void {
    $people = Employee::factory()->count(5)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
    $ana = $people->firstOrFail();
    $survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    Bus::fake();
    resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), User::factory()->create()->id);
    Bus::assertBatched(function (PendingBatch $batch): bool {
        $batch->jobs->each(fn (SendInvitations $job) => $job->handle());

        return true;
    });

    $html = sentEmailHtml(ClimateInvitation::class, (string) $ana->email);

    expect($html)->toContain('Todo lo suyo, en un solo lugar')
        ->toContain(route('portal.show', resolve(IssuePortalLink::class)->handle($ana->id)));
});

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
