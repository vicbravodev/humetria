<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\AcknowledgeRisks;
use App\Modules\Seating\Actions\RequestAcknowledgment;
use App\Modules\Seating\Actions\RequestAcknowledgmentsForCenter;
use App\Modules\Seating\Http\Controllers\PortalAcknowledgmentController;
use App\Modules\Seating\Jobs\SendRisksToConfirm;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Notifications\RisksToConfirm;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RequestAcknowledgment::class, RequestAcknowledgmentsForCenter::class, AcknowledgeRisks::class, SendRisksToConfirm::class, PortalAcknowledgmentController::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

function signedFor(string $employeeId, array $overrides = []): RiskAssessment
{
    return RiskAssessment::factory()->signed()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employeeId, 'position_id' => test()->position->id, 'work_center_id' => test()->center->id, ...$overrides]);
}

it('queues the link once when an assessment is signed', function (): void {
    Queue::fake();
    $draft = RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id,
        'scores' => ['standing_time' => 1, 'posture_change' => 1, 'surface' => 1, 'footwear' => 1, 'movement_space' => 1, 'discomfort' => 1, 'pauses' => 1], 'other_aspects' => 'Ninguno.', 'verified_on' => '2026-02-27']);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

    $this->actingAs($cora)->post(route('tenant.seating.assessments.sign', [$this->tenant, $draft->id]))->assertSessionHasNoErrors();
    $this->post(route('tenant.seating.assessments.sign', [$this->tenant, $draft->id]));

    Queue::assertPushed(SendRisksToConfirm::class, 1);
    Queue::assertPushed(SendRisksToConfirm::class, fn (SendRisksToConfirm $job): bool => $job->tenantId === $this->tenant->id && $job->employeeId === $this->ana->id);
});

it('emails the person, as usted, with their link to the Ley Silla page, when the job runs', function (): void {
    Notification::fake();
    signedFor($this->ana->id);

    app()->call([new SendRisksToConfirm($this->tenant->id, $this->ana->id), 'handle']);

    Notification::assertSentOnDemand(RisksToConfirm::class, function (RisksToConfirm $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'ana@acme.test'
            && $channels === ['mail']
            && str_contains(implode(' ', [...$mail->introLines, $mail->actionUrl ?? '']), route('portal.seating.show', $this->token));
    });
});

it('sends nothing when the person already confirmed, has no email, left, or the company may not be used', function (string $case): void {
    Notification::fake();
    $assessment = signedFor($this->ana->id);
    match ($case) {
        'confirmed' => RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'assessment_id' => $assessment->id]),
        'no email' => $this->ana->forceFill(['email' => null])->save(),
        'left' => $this->ana->forceFill(['deactivated_at' => now()])->save(),
        'blocked' => Tenant::query()->whereKey($this->tenant->id)->update(suspension()),
    };

    app()->call([new SendRisksToConfirm($this->tenant->id, $this->ana->id), 'handle']);

    Notification::assertNothingSent();
})->with(['confirmed', 'no email', 'left', 'blocked']);

it('lets RH resend the link of a signed assessment and send to everyone in a center who has not confirmed', function (): void {
    Queue::fake();
    $assessment = signedFor($this->ana->id);
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'email' => 'beto@acme.test']);
    $cris = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'email' => null]);
    $dora = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'email' => 'dora@acme.test']);
    foreach ([$beto, $cris, $dora] as $employee) {
        PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'position_id' => $this->position->id]);
        $signed = signedFor($employee->id);
    }
    RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $dora->id, 'assessment_id' => $signed->id]);
    $this->actingAs($this->hr);

    $this->post(route('tenant.seating.acknowledgments.resend', [$this->tenant, $assessment->id]))->assertRedirect();
    Queue::assertPushed(SendRisksToConfirm::class, 1);

    $this->post(route('tenant.seating.acknowledgments.batch', [$this->tenant, $this->center->id]))->assertRedirect();
    // Ana and Beto have an email and nothing confirmed; Cris has no email; Dora confirmed. Ana's email is already waiting from
    // the resend (a person has at most one waiting), so only Beto's is new.
    Queue::assertPushed(SendRisksToConfirm::class, 2);
    Queue::assertPushed(SendRisksToConfirm::class, fn (SendRisksToConfirm $job): bool => $job->employeeId === $beto->id);
});

it('lets only manage-seating resend or send in batch', function (TenantRole $role): void {
    Queue::fake();
    $assessment = signedFor($this->ana->id);
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);
    $this->actingAs($user);

    $this->post(route('tenant.seating.acknowledgments.resend', [$this->tenant, $assessment->id]))->assertForbidden();
    $this->post(route('tenant.seating.acknowledgments.batch', [$this->tenant, $this->center->id]))->assertForbidden();
    Queue::assertNothingPushed();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('does not resend a draft', function (): void {
    Queue::fake();
    $draft = RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($this->hr)->post(route('tenant.seating.acknowledgments.resend', [$this->tenant, $draft->id]))->assertNotFound();
    Queue::assertNothingPushed();
});

it('shows the person what to confirm, and records the confirmation with date and time, once', function (): void {
    $assessment = signedFor($this->ana->id);

    $this->get(route('portal.seating.show', $this->token))->assertInertia(fn (Assert $page) => $page
        ->where('acknowledgment.assessmentId', $assessment->id)->where('acknowledgment.levelLabel', 'Bajo')->where('acknowledgment.measures', []));

    $this->post(route('portal.seating.acknowledgments.store', $this->token))->assertRedirect(route('portal.seating.show', $this->token))->assertSessionHasNoErrors();
    $this->travelTo('2026-03-01 13:00:00');
    $this->post(route('portal.seating.acknowledgments.store', $this->token))->assertSessionHasNoErrors();

    $row = RiskAcknowledgment::query()->sole();
    expect($row->employee_id)->toBe($this->ana->id)->and($row->assessment_id)->toBe($assessment->id)
        ->and($row->acknowledged_at->toDateTimeString())->toBe('2026-03-01 12:00:00');
    $this->get(route('portal.seating.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('acknowledgment', null)->where('acknowledgedOn', '2026-03-01'));
});

it('does not ask again when the assessment is marked for reassessment, but asks again for a new signed one', function (): void {
    $first = signedFor($this->ana->id);
    $this->post(route('portal.seating.acknowledgments.store', $this->token));
    $first->forceFill(['reassessment_due' => true])->save();

    $this->get(route('portal.seating.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('acknowledgment', null));

    $second = signedFor($this->ana->id, ['signed_at' => now()->addDay()]);
    $this->get(route('portal.seating.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('acknowledgment.assessmentId', $second->id));
});

it('confirms only the current signed assessment of the person, never another one', function (): void {
    $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    signedFor($other->id);

    $this->post(route('portal.seating.acknowledgments.store', $this->token))->assertNotFound();

    expect(RiskAcknowledgment::query()->count())->toBe(0);
});

it('answers 404 for a link that is not valid', function (): void {
    signedFor($this->ana->id);

    $this->post(route('portal.seating.acknowledgments.store', 'nope'))->assertNotFound();
});

it('refuses in the database a second confirmation of the same assessment', function (): void {
    $assessment = signedFor($this->ana->id);
    RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'assessment_id' => $assessment->id]);

    expect(fn () => DB::transaction(fn () => RiskAcknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'assessment_id' => $assessment->id])))
        ->toThrow(QueryException::class, 'risk_acknowledgments_assessment_unique');
});

it('holds the mail of the whole center before queueing: what does not fit queues nothing and says how many are missing', function (): void {
    Queue::fake();
    signedFor($this->ana->id);
    withMailQuota($this->tenant, 0);

    expect(fn () => resolve(RequestAcknowledgmentsForCenter::class)->handle($this->center->id))
        ->toThrow(App\Modules\Billing\Exceptions\MailQuotaExceeded::class, 'faltan 1');

    Queue::assertNothingPushed();
});

it('queues the mail of the center when it fits the quota exactly', function (): void {
    Queue::fake();
    signedFor($this->ana->id);
    withMailQuota($this->tenant, 1);

    expect(resolve(RequestAcknowledgmentsForCenter::class)->handle($this->center->id))->toBe(['queued' => 1, 'withoutEmail' => 0]);

    Queue::assertPushed(SendRisksToConfirm::class, 1);
});
