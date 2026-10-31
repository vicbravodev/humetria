<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\SendTeleworkDigests;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\DigestDelivery;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Notifications\TeleworkDigest;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(SendTeleworkDigests::class);

beforeEach(function (): void {
    // 08:00 in Mexico City.
    $this->travelTo('2026-12-16 14:00:00');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->other = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

/** A workplace of an active teleworker of the center, validated at `$validatedAt` (null: never reviewed). */
function digestWorkplace(WorkCenter $center, ?string $validatedAt = null, string $state = 'valid'): Workplace
{
    $employee = Employee::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id]);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $center->tenant_id, 'employee_id' => $employee->id]);
    $workplace = WorkplaceFactory::new()->create(['tenant_id' => $center->tenant_id, 'teleworker_id' => $person->id]);

    if ($validatedAt !== null) {
        $review = WorkplaceReviewFactory::new()->decidedAt($validatedAt);
        $review = $state === 'plan' ? $review->withPlan() : $review;
        $review->create(['tenant_id' => $center->tenant_id, 'workplace_id' => $workplace->id, ...($state === 'plan' ? ['plan_due_on' => '2026-12-15'] : [])]);
    }

    return $workplace;
}

/** @return list<string> the lines of the single digest sent to `$email`, or [] when none went. */
function digestLinesFor(string $email): array
{
    $lines = [];
    Notification::assertSentOnDemand(TeleworkDigest::class, function (TeleworkDigest $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$lines): bool {
        if ($notifiable->routes['mail'] !== $email) {
            return false;
        }

        $mail = $notification->toMail($notifiable);
        $lines = array_slice(array_map(strval(...), $mail->introLines), 1);

        return $mail->subject === 'Resumen de teletrabajo' && $channels === ['mail'];
    });

    return $lines;
}

function noDigestFor(string $email): void
{
    Notification::assertNotSentTo(new AnonymousNotifiable, TeleworkDigest::class, fn (TeleworkDigest $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $email);
}

it('tells the owner what is waiting: expired workplaces, trainings, notices and lists', function (): void {
    // Expired at midnight today (validated 2025-12-16 for 12 months): new since yesterday's 08:00.
    digestWorkplace($this->center, '2025-12-16 18:00:00');
    // Expired a week ago: not new.
    digestWorkplace($this->center, '2025-12-09 18:00:00');
    $due = digestWorkplace($this->center);
    Teleworker::query()->whereKey($due->teleworker_id)->update(['started_at' => '2025-12-30 12:00:00']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $due->teleworker_id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $due->teleworker_id, 'reviewed_at' => now(), 'reviewed_by' => $this->owner->id, 'review_note' => 'Revisado.']);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $due->id]);
    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $due->id]);

    expect(resolve(SendTeleworkDigests::class)->handle())->toBe(1);

    expect(digestLinesFor($this->owner->email))->toBe([
        '1 lugar de trabajo venció y quedó pendiente de validar.',
        '1 persona tiene su capacitación por vencer o vencida.',
        '1 aviso por revisar.',
        '1 lista por revisar.',
    ]);
});

it('tells each Comisión member about their own centers only: lists, notices, overdue plans and expired workplaces', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $mine = digestWorkplace($this->center, '2026-01-15 18:00:00', 'plan');
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $mine->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $mine->teleworker_id]);
    digestWorkplace($this->center, '2025-12-16 18:00:00');
    $elsewhere = digestWorkplace($this->other, '2026-01-15 18:00:00', 'plan');
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $elsewhere->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $elsewhere->teleworker_id]);

    resolve(SendTeleworkDigests::class)->handle();

    expect(digestLinesFor($cora->email))->toBe([
        '1 lugar de trabajo venció y quedó pendiente de validar.',
        '1 lista por revisar.',
        '1 aviso por revisar.',
        '1 plan de acción vencido.',
    ]);
});

it('does not count a plan that was reviewed again, is not due yet or belongs to a retired workplace', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $again = digestWorkplace($this->center, '2026-01-15 18:00:00', 'plan');
    WorkplaceReviewFactory::new()->decidedAt('2026-12-01 18:00:00')->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $again->id]);
    $notYet = digestWorkplace($this->center, '2026-01-15 18:00:00', 'plan');
    App\Modules\Telework\Models\WorkplaceReview::query()->where('workplace_id', $notYet->id)->update(['plan_due_on' => '2026-12-16']);
    digestWorkplace($this->center, '2026-01-15 18:00:00', 'plan')->update(['retired_at' => now()]);

    expect(resolve(SendTeleworkDigests::class)->handle())->toBe(0);
});

it('sends one digest per recipient per day and the next day again when something is still waiting', function (): void {
    $workplace = digestWorkplace($this->center);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $workplace->teleworker_id]);

    $first = resolve(SendTeleworkDigests::class)->handle();
    $again = resolve(SendTeleworkDigests::class)->handle();
    $this->travelTo('2026-12-17 14:00:00');
    $next = resolve(SendTeleworkDigests::class)->handle();

    expect([$first, $again, $next])->toBe([1, 0, 1])
        ->and(DigestDelivery::query()->count())->toBe(2);
    Notification::assertSentOnDemandTimes(TeleworkDigest::class, 2);
});

it('sends nothing, and records nothing, when every section is empty', function (): void {
    digestWorkplace($this->center, '2026-10-01 18:00:00');

    expect(resolve(SendTeleworkDigests::class)->handle())->toBe(0)
        ->and(DigestDelivery::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('counts an expiry only once: after the last digest it is no longer new', function (): void {
    digestWorkplace($this->center, '2025-12-16 18:00:00');

    $first = resolve(SendTeleworkDigests::class)->handle();
    $this->travelTo('2026-12-17 14:00:00');
    $second = resolve(SendTeleworkDigests::class)->handle();

    expect([$first, $second])->toBe([1, 0]);
});

it('skips blocked companies and companies without an owner, and reaches the rest', function (): void {
    $blocked = Tenant::factory()->create(['deactivated_at' => now()]);
    $blockedCenter = WorkCenter::factory()->for($blocked)->create();
    ownerOf($blocked);
    workIn($blocked);
    WorkerNoticeFactory::new()->create(['tenant_id' => $blocked->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $blocked->id, 'work_center_id' => $blockedCenter->id, 'employee_id' => Employee::factory()->create(['tenant_id' => $blocked->id, 'work_center_id' => $blockedCenter->id])->id])->id]);
    $orphan = Tenant::factory()->create();
    $orphanCenter = WorkCenter::factory()->for($orphan)->create();
    workIn($orphan);
    WorkerNoticeFactory::new()->create(['tenant_id' => $orphan->id, 'teleworker_id' => TeleworkerFactory::new()->active()->create(['tenant_id' => $orphan->id, 'work_center_id' => $orphanCenter->id, 'employee_id' => Employee::factory()->create(['tenant_id' => $orphan->id, 'work_center_id' => $orphanCenter->id])->id])->id]);
    workIn($this->tenant);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => digestWorkplace($this->center)->teleworker_id]);

    expect(resolve(SendTeleworkDigests::class)->handle())->toBe(1);
});

it('logs how many digests went, without personal data', function (): void {
    Log::spy();
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => digestWorkplace($this->center)->teleworker_id]);

    resolve(SendTeleworkDigests::class)->handle();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.digests_sent' && $context === ['owner' => 1, 'committee' => 1])->once();
});

it('keeps the delivery record inside its company', function (): void {
    DigestDelivery::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = Tenant::factory()->create();
    DigestDelivery::factory()->create(['tenant_id' => $other->id]);

    expect(DigestDelivery::query()->count())->toBe(1);
    workIn($other);
    expect(DigestDelivery::query()->count())->toBe(1)->and(DigestDelivery::query()->sole()->tenant_id)->toBe($other->id);
});
