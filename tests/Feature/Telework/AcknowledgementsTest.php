<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\PublishDocument;
use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Jobs\SendAcknowledgementRequest;
use App\Modules\Telework\Listeners\RequestOnPublication;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Notifications\DocumentsToConfirm;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

mutates(RequestAcknowledgements::class, RequestOnPublication::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->other = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

function staffer(WorkCenter $center, array $state = []): Employee
{
    return EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, ...$state]);
}

/** @return list<TeleworkDocument> */
function publishDocument(DocumentKind $kind, WorkCenter ...$centers): array
{
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, "%PDF-1.4\n%%EOF\n");

    return resolve(PublishDocument::class)->handle($kind, $path, array_map(fn (WorkCenter $center): string => $center->id, $centers), [], test()->hr->id);
}

it('asks every active employee of the center to acknowledge a published policy', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    $bea = staffer($this->center, ['email' => null]);
    staffer($this->center, ['deactivated_at' => now()]);
    staffer($this->other);

    [$document] = publishDocument(DocumentKind::Policy, $this->center);

    $requests = Acknowledgement::query()->where('document_id', $document->id)->get();
    expect($requests->pluck('employee_id')->sort()->values()->all())->toBe(collect([$ana->id, $bea->id])->sort()->values()->all())
        ->and($requests->every(fn (Acknowledgement $request): bool => $request->acknowledged_at === null && $request->user_id === null && $request->channel === null))->toBeTrue();
    Queue::assertPushed(SendAcknowledgementRequest::class, 1);
    Queue::assertPushed(SendAcknowledgementRequest::class, fn (SendAcknowledgementRequest $job): bool => $job->tenantId === $this->tenant->id && $job->employeeId === $ana->id && $job->userId === null);
});

it('asks nothing for the other kinds', function (): void {
    staffer($this->center);

    publishDocument(DocumentKind::Reversibility, $this->center);

    expect(Acknowledgement::query()->count())->toBe(0);
});

it('asks the roster and the committee for risk information', function (): void {
    Queue::fake();
    $onRoster = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => staffer($this->center)->id]);
    TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => staffer($this->center)->id]);
    staffer($this->center);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->other->id, 'user_id' => $cora->id]);

    [$document] = publishDocument(DocumentKind::RiskInformation, $this->center);

    $requests = Acknowledgement::query()->where('document_id', $document->id)->get();
    expect($requests)->toHaveCount(2)
        ->and($requests->pluck('employee_id')->filter()->values()->all())->toBe([$onRoster->employee_id])
        ->and($requests->pluck('user_id')->filter()->values()->all())->toBe([$cora->id]);
    Queue::assertPushed(SendAcknowledgementRequest::class, 2);
    Queue::assertPushed(SendAcknowledgementRequest::class, fn (SendAcknowledgementRequest $job): bool => $job->userId === $cora->id && $job->employeeId === null);
});

it('asks again when a new version is published and keeps the old requests', function (): void {
    $ana = staffer($this->center);
    [$first] = publishDocument(DocumentKind::Policy, $this->center);
    Acknowledgement::query()->where('document_id', $first->id)->update(['channel' => 'portal', 'acknowledged_at' => now()]);

    [$second] = publishDocument(DocumentKind::Policy, $this->center);

    expect(Acknowledgement::query()->where('document_id', $first->id)->where('employee_id', $ana->id)->sole()->acknowledged_at)->not->toBeNull()
        ->and(Acknowledgement::query()->where('document_id', $second->id)->where('employee_id', $ana->id)->sole()->acknowledged_at)->toBeNull();
});

it('does not ask twice for the same version', function (): void {
    Queue::fake();
    staffer($this->center);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);

    expect(resolve(RequestAcknowledgements::class)->handle([$document->id]))->toBe(0)
        ->and(Acknowledgement::query()->count())->toBe(1);
    Queue::assertPushed(SendAcknowledgementRequest::class, 1);
});

it('logs telework.acknowledgement_requested with ids and counts only', function (): void {
    Log::spy();
    $ana = staffer($this->center);

    [$document] = publishDocument(DocumentKind::Policy, $this->center);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.acknowledgement_requested'
        && $context === ['document_ids' => [$document->id], 'employees' => 1, 'users' => 0])->once();
    expect(json_encode(Acknowledgement::query()->get()->toArray(), JSON_THROW_ON_ERROR))->toContain($ana->id);
});

it('mails the employee a fresh portal link made as the job runs, replacing the old one', function (): void {
    Notification::fake();
    $ana = staffer($this->center, ['name' => 'Ana Ruiz']);
    $old = resolve(App\Modules\Telework\Actions\IssuePortalLink::class)->handle($ana->id);
    publishDocument(DocumentKind::Policy, $this->center);

    $job = new SendAcknowledgementRequest($this->tenant->id, $ana->id, null);
    app()->call($job->handle(...));

    $url = null;
    Notification::assertSentOnDemand(DocumentsToConfirm::class, function (DocumentsToConfirm $notification, array $channels, AnonymousNotifiable $notifiable) use (&$url, $ana): bool {
        $mail = $notification->toMail($notifiable);
        $url = $mail->actionUrl;

        return $mail->subject === 'Tiene documentos por confirmar' && $notifiable->routes['mail'] === $ana->email && $channels === ['mail'];
    });
    expect(PortalLink::query()->sole()->token_hash)->toBe(hash('sha256', basename($url)))
        ->and(PortalLink::query()->where('token_hash', hash('sha256', $old))->exists())->toBeFalse()
        ->and($job)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and(serialize($job))->not->toContain(basename($url));
});

it('mails a Comisión member the link to the documents page', function (): void {
    Notification::fake();
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    publishDocument(DocumentKind::RiskInformation, $this->center);

    app()->call((new SendAcknowledgementRequest($this->tenant->id, null, $cora->id))->handle(...));

    Notification::assertSentOnDemand(DocumentsToConfirm::class, fn (DocumentsToConfirm $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $cora->email
        && $notification->toMail($notifiable)->actionUrl === route('tenant.telework.documents.index', $this->tenant));
});

it('sends nothing when nothing is pending, the person left or the company is blocked', function (): void {
    Notification::fake();
    Queue::fake();
    $ana = staffer($this->center);
    $job = new SendAcknowledgementRequest($this->tenant->id, $ana->id, null);

    app()->call($job->handle(...));
    publishDocument(DocumentKind::Policy, $this->center);
    $ana->forceFill(['deactivated_at' => now()])->save();
    app()->call($job->handle(...));
    $ana->forceFill(['deactivated_at' => null])->save();
    Tenant::query()->whereKey($this->tenant->id)->update(['deactivated_at' => now()]);
    app()->call($job->handle(...));

    Notification::assertNothingSent();
    expect(PortalLink::query()->count())->toBe(0);
});
