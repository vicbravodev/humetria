<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Notifications\PolicyToConfirm;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

mutates(SendPolicyReminder::class, PolicyToConfirm::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana Ruiz', 'email' => 'ana@acme.test']);
    $this->policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'work_center_id' => $this->plant->id]);
    workIn($this->tenant);
});

function remindAna(): void
{
    resolve(SendPolicyReminder::class, ['tenantId' => test()->tenant->id, 'employeeId' => test()->ana->id])->handle(...array_map(resolve(...), [
        App\Modules\Tenancy\Contracts\Companies::class,
        App\Modules\Organization\Contracts\Roster::class,
        PortalAccess::class,
        App\Modules\Disclosure\Support\PolicyStanding::class,
    ]));
}

it('queues once per person, after the commit, with only ids in the job', function (): void {
    $job = new SendPolicyReminder($this->tenant->id, $this->ana->id);

    expect($job)->toBeInstanceOf(ShouldQueue::class)->toBeInstanceOf(ShouldQueueAfterCommit::class)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe("{$this->tenant->id}:{$this->ana->id}")
        ->and(SendPolicyReminder::DELAY)->toBe(120)
        ->and(get_object_vars($job))->not->toHaveKey('token');
});

it('mails the person their personal link to the policy waiting for them, as «usted»', function (): void {
    Notification::fake();
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'employee_id' => $this->ana->id]);

    remindAna();

    Notification::assertSentOnDemand(PolicyToConfirm::class, function (PolicyToConfirm $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);
        $token = resolve(PortalAccess::class)->token($this->ana->id);

        return $notifiable->routes['mail'] === 'ana@acme.test'
            && $channels === ['mail']
            && $mail->actionUrl === route('portal.policy.show', $token)
            && str_contains($mail->subject ?? '', 'política de prevención')
            && $mail->greeting === 'Hola, Ana Ruiz'
            && str_contains(implode(' ', $mail->introLines), 'Acme');
    });
});

it('sends nothing when there is nothing waiting or nobody to tell', function (Closure $arrange): void {
    Notification::fake();
    $arrange($this);

    remindAna();

    Notification::assertNothingSent();
})->with([
    'it was already confirmed' => [fn ($test) => Acknowledgment::factory()->portal()->create(['tenant_id' => $test->tenant->id, 'document_id' => $test->policy->id, 'employee_id' => $test->ana->id])],
    'the person was never asked' => [fn ($test) => null],
    'the person has no email' => [function ($test): void {
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $test->policy->id, 'employee_id' => $test->ana->id]);
        $test->ana->update(['email' => null]);
    }],
    'the person left' => [function ($test): void {
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $test->policy->id, 'employee_id' => $test->ana->id]);
        $test->ana->update(['deactivated_at' => now()]);
    }],
    'the company may not be used' => [function ($test): void {
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $test->policy->id, 'employee_id' => $test->ana->id]);
        Tenant::query()->whereKey($test->tenant->id)->update(['deactivated_at' => now()]);
    }],
    'the version asked is no longer in force' => [function ($test): void {
        Acknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $test->policy->id, 'employee_id' => $test->ana->id]);
        $newer = Document::factory()->published()->policy()->create(['tenant_id' => $test->tenant->id, 'version' => 2, 'published_by' => $test->hr->id]);
        DocumentCenter::factory()->create(['tenant_id' => $test->tenant->id, 'document_id' => $newer->id, 'work_center_id' => $test->plant->id]);
    }],
]);

it('queues a reminder for each person asked who has an email, delayed so a burst becomes one', function (): void {
    Queue::fake();
    $noEmail = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'email' => null]);

    resolve(RequestAcknowledgments::class)->handle($this->policy->id);

    Queue::assertPushed(SendPolicyReminder::class, 1);
    Queue::assertPushed(SendPolicyReminder::class, fn (SendPolicyReminder $job): bool => $job->employeeId === $this->ana->id && $job->tenantId === $this->tenant->id && $job->delay === 120);
    expect(Acknowledgment::query()->where('employee_id', $noEmail->id)->count())->toBe(1);
});

it('queues nothing when nobody new was asked', function (): void {
    resolve(RequestAcknowledgments::class)->handle($this->policy->id);
    Queue::fake();

    resolve(RequestAcknowledgments::class)->handle($this->policy->id);

    Queue::assertNothingPushed();
});

it('never writes the link or the email in the log', function (): void {
    Notification::fake();
    Log::spy();
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'employee_id' => $this->ana->id]);

    remindAna();

    Log::shouldNotHaveReceived('info', fn (string $message, array $context = []): bool => str_contains(json_encode($context) ?: '', 'ana@acme.test') || str_contains(json_encode($context) ?: '', resolve(PortalAccess::class)->token($this->ana->id)));
});
