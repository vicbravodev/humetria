<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Actions\LaunchSurvey;
use App\Modules\Engagement\Actions\RemindPending;
use App\Modules\Engagement\Enums\InvitationStatus;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Jobs\SendInvitations;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\SurveyInvitation;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\PendingBatch;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

mutates(RemindPending::class, SendInvitations::class, SurveyInvitation::class, SurveyClock::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    Notification::fake();
    RateLimiter::clear('survey-invitations');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes del Bajío']);
    $this->user = User::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** A survey launched to `$people` people, its links not sent yet; returns the jobs of the one batch. */
function launchedSurvey(Tenant $tenant, WorkCenter $plant, Area $area, User $user, int $people): array
{
    Employee::factory()->count($people)->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'area_id' => $area->id]);
    $survey = Survey::factory()->create(['tenant_id' => $tenant->id]);
    Bus::fake();
    resolve(LaunchSurvey::class)->handle($survey->id, new CarbonImmutable('2026-11-20'), $user->id);

    return [$survey->refresh(), batchJobs()];
}

/** @return list<SendInvitations> */
function batchJobs(): array
{
    $jobs = [];
    Bus::assertBatched(function (PendingBatch $batch) use (&$jobs): bool {
        $jobs = $batch->jobs->all();

        return true;
    });

    return $jobs;
}

/** @return array<string, string> the link of each email sent, by address */
function sentLinks(string $notification = SurveyInvitation::class): array
{
    $links = [];
    Notification::assertSentOnDemand($notification, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable) use (&$links): bool {
        $links[(string) $notifiable->routes['mail']] = (string) $mail->toMail($notifiable)->actionUrl;

        return true;
    });

    return $links;
}

it('sends nothing while the request that launches runs: the emails wait in jobs', function (): void {
    launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);

    Notification::assertNothingSent();
});

it('emails each person of its block the link of the survey, and keeps only the hash of the token', function (): void {
    [$survey, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);

    $jobs[0]->handle();

    $links = sentLinks();
    expect($links)->toHaveCount(20);
    foreach (Invitation::query()->with('survey')->get() as $invitation) {
        $email = Employee::query()->findOrFail($invitation->employee_id)->email;
        $token = $invitation->surveyToken();
        expect($links[(string) $email])->toBe(route('engagement.survey.show', $token))
            ->and($invitation->token_hash)->toBe(Invitation::hashToken($token))
            ->and($invitation->invitation_status)->toBe(InvitationStatus::Sent)
            ->and($invitation->invited_at?->toIso8601String())->toBe('2026-11-09T15:00:00+00:00');
    }
});

it('sends once even when the job runs again', function (): void {
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);

    $jobs[0]->handle();
    $jobs[0]->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 20);
});

it('reminds only those who have not answered, with the same link', function (): void {
    [$survey, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    $jobs[0]->handle();
    $first = sentLinks();
    Invitation::query()->limit(3)->update(['answered' => true]);
    Invitation::query()->where('answered', false)->limit(1)->update(['cancelled' => true]);
    Notification::fake();
    Bus::fake();

    $reminded = resolve(RemindPending::class)->handle($survey->id);

    expect($reminded)->toBe(16);
    $again = batchJobs();
    $again[0]->handle();
    $second = sentLinks();
    expect($second)->toHaveCount(16)
        ->and(array_diff_key($second, $first))->toBe([])
        ->and(array_filter($second, fn (string $link, string $email): bool => $first[$email] !== $link, ARRAY_FILTER_USE_BOTH))->toBe([]);
});

it('refuses to remind a survey that is not active', function (): void {
    $draft = Survey::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(RemindPending::class)->handle($draft->id))->toThrow(SurveyStateChanged::class);
});

it('marks the person without an email and counts them', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id, 'email' => null]);

    [$survey] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 19);

    expect(Invitation::query()->where('survey_id', $survey->id)->get()->countBy(fn (Invitation $i): string => $i->invitation_status->value ?? 'none')->all())
        ->toEqual(['queued' => 19, 'no_email' => 1]);
});

it('sends no link while the company is blocked, and marks who was waiting as failed so a reminder reaches them later', function (): void {
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    $this->tenant->update(['restriction' => 'suspended', 'restriction_reason' => 'Prueba', 'restricted_at' => now()]);

    $jobs[0]->handle();

    Notification::assertNothingSent();
    expect(Invitation::query()->get()->every(fn (Invitation $i): bool => $i->invitation_status === InvitationStatus::Failed && $i->token_hash === null))->toBeTrue();
});

it('sends no link for a survey that was closed or whose last day passed', function (string $change): void {
    [$survey, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    $survey->update($change === 'closed'
        ? ['status' => SurveyStatus::Closed, 'closed_at' => now()]
        : ['closes_on' => '2026-11-08']);

    $jobs[0]->handle();

    Notification::assertNothingSent();
    expect(Invitation::query()->where('invitation_status', InvitationStatus::Failed)->count())->toBe(20);
})->with(['closed', 'late']);

it('leaves to a newer batch whoever it queued again', function (): void {
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    Invitation::query()->limit(5)->update(['invitation_batch' => (string) Str::uuid()]);

    $jobs[0]->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 15);
});

it('waits for the next minute when the emails per minute are used up', function (): void {
    config(['campaigns.invitations_per_minute' => 3]);
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    $jobs[0]->withFakeQueueInteractions();

    $jobs[0]->handle();

    Notification::assertSentOnDemandTimes(SurveyInvitation::class, 3);
    $jobs[0]->assertReleased();
    expect(Invitation::query()->where('invitation_status', InvitationStatus::Sent)->count())->toBe(3);
});

it('marks as failed whoever was still waiting when it gives up, logging only the class of the error', function (): void {
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);
    Invitation::query()->limit(4)->update(['invitation_status' => InvitationStatus::Sent]);
    Log::spy();

    $jobs[0]->failed(new RuntimeException('550 ana@acme.mx rejected'));

    expect(Invitation::query()->get()->countBy(fn (Invitation $i): string => $i->invitation_status->value ?? 'none')->all())->toEqual(['sent' => 4, 'failed' => 16]);
    Log::shouldHaveReceived('warning')->with('engagement.invitations_failed', ['survey_id' => $jobs[0]->surveyId, 'failed' => 16, 'exception' => RuntimeException::class]);
});

it('never writes the token, its hash or an address in a log line', function (): void {
    $lines = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$lines): void {
        $lines[] = $log->message.json_encode($log->context);
    });
    [, $jobs] = launchedSurvey($this->tenant, $this->plant, $this->area, $this->user, 20);

    $jobs[0]->handle();
    resolve(RemindPending::class)->handle(Survey::query()->sole()->id);

    $text = implode("\n", $lines);
    expect($lines)->not->toBe([]);
    foreach (Invitation::query()->get() as $invitation) {
        expect($text)->not->toContain($invitation->surveyToken())->not->toContain((string) $invitation->token_hash);
    }
    expect($text)->not->toContain('@')->and($text)->toContain('engagement.survey_reminded');
});

it('writes the invitation and the reminder in «usted», with the minimum of the group and no promise of secrecy', function (bool $reminder): void {
    $company = new App\Modules\Tenancy\Data\CompanySummary('t', 'Autopartes del Bajío', 'autopartes', false);
    $mail = new SurveyInvitation($reminder, 'tok', 'Ana', $company, 'rh@acme.mx', CarbonImmutable::parse('2026-11-12'), App\Modules\Engagement\Enums\SurveyKind::Climate);
    $message = $mail->toMail(new AnonymousNotifiable);
    $html = (string) $message->render();

    expect($message->subject)->toBe($reminder ? 'Le quedan 4 días para dar su opinión' : 'Su opinión sobre el trabajo en Autopartes del Bajío')
        ->and($message->actionText)->toBe('Contestar la encuesta')
        ->and($html)->toContain('El sistema no guarda quién contestó qué y solo muestra resultados de grupos de 5 personas o más')
        ->toContain('12 minutos')
        ->toContain(route('engagement.survey.show', 'tok'))
        ->not->toMatch('/anónim|nadie sabr|eNPS|Net Promoter/iu');
})->with([false, true]);

it('says today is the last day on the last day', function (): void {
    $company = new App\Modules\Tenancy\Data\CompanySummary('t', 'Autopartes', 'autopartes', false);

    $subject = (new SurveyInvitation(true, 'tok', 'Ana', $company, null, CarbonImmutable::parse('2026-11-09'), App\Modules\Engagement\Enums\SurveyKind::Climate))->toMail(new AnonymousNotifiable)->subject;

    expect($subject)->toBe('Hoy es su último día para dar su opinión');
});

it('counts the days to answer by the local calendar, today included', function (): void {
    expect(SurveyClock::daysToAnswer(CarbonImmutable::parse('2026-11-09')))->toBe(1)
        ->and(SurveyClock::daysToAnswer(CarbonImmutable::parse('2026-11-12')))->toBe(4)
        ->and(SurveyClock::daysToAnswer(CarbonImmutable::parse('2026-11-01')))->toBe(1);

    Carbon::setTestNow('2026-11-10 03:00:00');
    expect(SurveyClock::daysToAnswer(CarbonImmutable::parse('2026-11-12')))->toBe(4);
});

it('reaches only by email', function (): void {
    $notification = new SurveyInvitation(false, 'tok', 'Ana', new App\Modules\Tenancy\Data\CompanySummary('t', 'A', 'a', false), null, CarbonImmutable::parse('2026-11-12'), App\Modules\Engagement\Enums\SurveyKind::Climate);

    expect($notification->via(Notification::route('mail', 'a@b.mx')))->toBe(['mail'])
        ->and($notification->via(new User))->toBe([]);
});
