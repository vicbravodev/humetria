<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Actions\PublishFeedback;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Jobs\SendFeedbackPublished;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Notifications\FeedbackPublished;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

mutates(PublishFeedback::class, SendFeedbackPublished::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->survey = climateResultsSurvey($this->tenant, 6);
});

/** A person invited to the survey, with the email and state asked. */
function invitedToClimate(Survey $survey, string $email, array $employee = [], bool $cancelled = false): Employee
{
    $person = Employee::factory()->create([...$employee, 'tenant_id' => $survey->tenant_id, 'work_center_id' => test()->plant->id, 'email' => $email]);
    Invitation::factory()->create(['tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id, 'employee_id' => $person->id, 'cancelled' => $cancelled]);

    return $person;
}

function climateCommitment(Survey $survey, array $state = []): Commitment
{
    return Commitment::factory()->create([...$state, 'tenant_id' => $survey->tenant_id, 'survey_id' => $survey->id]);
}

it('does not publish a survey with no commitment, nor one with only dropped commitments, nor one still open', function (): void {
    $dropped = climateCommitment($this->survey, ['status' => CommitmentStatus::Dropped]);
    $open = climateResultsSurvey($this->tenant, 6, closed: false);
    climateCommitment($open);

    expect(fn () => resolve(PublishFeedback::class)->handle($this->survey->id, $this->hr->id))->toThrow(ValidationException::class)
        ->and(fn () => resolve(PublishFeedback::class)->handle($open->id, $this->hr->id))->toThrow(ValidationException::class);
    expect($this->survey->fresh()?->results_published_at)->toBeNull()
        ->and($dropped->fresh())->not->toBeNull();
});

it('publishes once: it dates the survey, audits it and queues the emails, and a second time changes nothing', function (): void {
    Queue::fake();
    climateCommitment($this->survey);

    $published = resolve(PublishFeedback::class)->handle($this->survey->id, $this->hr->id);
    $again = resolve(PublishFeedback::class)->handle($this->survey->id, $this->hr->id);

    expect($published->results_published_at)->not->toBeNull()
        ->and($again->results_published_at?->equalTo($published->results_published_at))->toBeTrue();
    Queue::assertPushed(SendFeedbackPublished::class, 1);
    expect(AuditEntry::query()->where('event', 'engagement.feedback_published')->count())->toBe(1);
});

it('emails the people invited who are active and have an address, each with their own link', function (): void {
    Notification::fake();
    $ana = invitedToClimate($this->survey, 'ana@acme.test', ['name' => 'Ana Ruiz']);
    invitedToClimate($this->survey, 'baja@acme.test', ['deactivated_at' => now()]);
    invitedToClimate($this->survey, 'cancelada@acme.test', cancelled: true);
    $noEmail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => null]);
    Invitation::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'employee_id' => $noEmail->id]);
    climateCommitment($this->survey);

    resolve(PublishFeedback::class)->handle($this->survey->id, $this->hr->id);

    Notification::assertSentOnDemand(FeedbackPublished::class, fn (FeedbackPublished $notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'ana@acme.test'
        && str_contains((string) $notification->toMail($notifiable)->actionUrl, resolve(PortalAccess::class)->token($ana->id).'/climate'));
    Notification::assertSentOnDemandTimes(FeedbackPublished::class, 1);
});

it('writes the mail in usted, from the company, without anything of the answers', function (): void {
    $company = new App\Modules\Tenancy\Data\CompanySummary('t', 'Acme', 'acme', false);
    $mail = (new FeedbackPublished('https://x.test/portal/tok/climate', 'Ana', $company))->toMail(new Illuminate\Notifications\AnonymousNotifiable);

    expect($mail->subject)->toBe('Lo que dijeron en la encuesta y lo que haremos')
        ->and($mail->actionUrl)->toBe('https://x.test/portal/tok/climate')
        ->and(implode(' ', [...$mail->introLines, ...$mail->outroLines]))->toContain('Acme')->toContain('solo suyo');
});

it('lets only RH publish, as a request, and only a survey of its own company', function (): void {
    Queue::fake();
    climateCommitment($this->survey);
    $other = Tenant::factory()->create();
    $surveyOfOther = climateResultsSurvey($other, 6);
    workIn($this->tenant);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route('tenant.climate.publish', [$this->tenant, $this->survey]))->assertForbidden();
    $this->actingAs($this->hr)->post(route('tenant.climate.publish', [$this->tenant, $surveyOfOther]))->assertNotFound();
    $this->post(route('tenant.climate.publish', [$this->tenant, Survey::factory()->closed()->pulse()->create(['tenant_id' => $this->tenant->id])]))->assertNotFound();
    $this->post(route('tenant.climate.publish', [$this->tenant, $this->survey]))->assertRedirect(route('tenant.climate.commitments.index', [$this->tenant, $this->survey]));

    expect($this->survey->fresh()?->results_published_at)->not->toBeNull();
});

it('answers 422 when there is nothing to publish', function (): void {
    $this->actingAs($this->hr)->post(route('tenant.climate.publish', [$this->tenant, $this->survey]))->assertSessionHasErrors('commitments');
});

it('leaves no commitment text in the log or the audit trail', function (): void {
    $path = storage_path('logs/publish-privacy-test.log');
    File::delete($path);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
    Queue::fake();
    climateCommitment($this->survey, ['will_do' => 'Texto secreto de la acción']);

    resolve(PublishFeedback::class)->handle($this->survey->id, $this->hr->id);

    expect(File::get($path))->toContain('engagement.feedback_published')->not->toContain('secreto');
    expect(json_encode(AuditEntry::query()->pluck('metadata'), JSON_THROW_ON_ERROR))->not->toContain('secreto');
    File::delete($path);
});
