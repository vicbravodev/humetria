<?php

declare(strict_types=1);

use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\Acknowledge;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\PublishDocument;
use App\Modules\Telework\Actions\RequestAcknowledgements;
use App\Modules\Telework\Enums\AckChannel;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Jobs\SendAcknowledgementRequest;
use App\Modules\Telework\Listeners\RequestOnPublication;
use App\Modules\Telework\Listeners\RequestPolicyFromNewStaff;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(RequestAcknowledgements::class, RequestOnPublication::class, RequestPolicyFromNewStaff::class, Acknowledge::class);

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

    $documents = resolve(PublishDocument::class)->handle($kind, $path, array_map(fn (WorkCenter $center): string => $center->id, $centers), [], test()->hr->id);
    // The requests are made by a queued listener: asked again here, so a faked queue sees them too (a second ask is a no-op).
    resolve(RequestAcknowledgements::class)->handle(array_map(fn (TeleworkDocument $document): string => $document->id, $documents));

    return $documents;
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
    $old = resolve(IssuePortalLink::class)->handle($ana->id);
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

it('asks new, reactivated and moved-in staff for the policy in force of their center', function (string $arrival): void {
    publishDocument(DocumentKind::Policy, $this->center);
    [$latest] = publishDocument(DocumentKind::Policy, $this->center);
    [$elsewhere] = publishDocument(DocumentKind::Policy, $this->other);
    Queue::fake();
    $newcomer = staffer($this->center);

    match ($arrival) {
        'created' => Event::dispatch(new EmployeeCreated($this->tenant->id, $newcomer->id, $this->center->id)),
        'reactivated' => Event::dispatch(new EmployeeReactivated($this->tenant->id, $newcomer->id)),
        'moved' => Event::dispatch(new EmployeeMoved($this->tenant->id, $newcomer->id, $this->other->id, $this->center->id)),
    };

    $request = Acknowledgement::query()->sole();
    expect($request->document_id)->toBe($latest->id)->and($request->employee_id)->toBe($newcomer->id)->and($request->document_id)->not->toBe($elsewhere->id);
    Queue::assertPushed(SendAcknowledgementRequest::class, fn (SendAcknowledgementRequest $job): bool => $job->employeeId === $newcomer->id);
})->with(['created', 'reactivated', 'moved']);

it('asks a newcomer once even if the arrival is announced twice, and nothing without a policy', function (): void {
    $lonely = staffer($this->other);
    Event::dispatch(new EmployeeCreated($this->tenant->id, $lonely->id, $this->other->id));
    expect(Acknowledgement::query()->count())->toBe(0);

    publishDocument(DocumentKind::Policy, $this->other);
    Acknowledgement::query()->delete();
    Event::dispatch(new EmployeeCreated($this->tenant->id, $lonely->id, $this->other->id));
    Event::dispatch(new EmployeeReactivated($this->tenant->id, $lonely->id));

    expect(Acknowledgement::query()->count())->toBe(1);
});

it('asks the risk information when someone joins the roster', function (): void {
    publishDocument(DocumentKind::RiskInformation, $this->center);
    $person = staffer($this->center);
    $period = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $person->id]);

    expect(resolve(RequestAcknowledgements::class)->forEmployee($period->employee_id, $period->work_center_id, DocumentKind::RiskInformation))->toBe(1)
        ->and(Acknowledgement::query()->where('employee_id', $person->id)->count())->toBe(1)
        ->and(resolve(RequestAcknowledgements::class)->forEmployee($person->id, $this->other->id, DocumentKind::RiskInformation))->toBe(0);
});

/** The portal token of an employee who was asked for the policy of the center. */
function portalOf(Employee $employee): string
{
    return resolve(IssuePortalLink::class)->handle($employee->id);
}

it('acknowledges from the portal with the time, the address and the channel', function (): void {
    Queue::fake();
    $this->travelTo('2026-05-04 15:30:00');
    $ana = staffer($this->center);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $portal = resolve(OpenPortal::class)->handle(portalOf($ana));

    resolve(Acknowledge::class)->handle($portal, $document->id, '203.0.113.9');

    $request = Acknowledgement::query()->sole();
    expect($request->acknowledged_at?->toIso8601String())->toBe('2026-05-04T15:30:00+00:00')
        ->and($request->ip)->toBe('203.0.113.9')
        ->and($request->channel)->toBe(AckChannel::Portal)
        ->and($request->employee_id)->toBe($ana->id);
});

it('acknowledges once: the second time is refused and nothing changes', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $portal = resolve(OpenPortal::class)->handle(portalOf($ana));
    resolve(Acknowledge::class)->handle($portal, $document->id, '203.0.113.9');
    $this->travelTo(now()->addDay());

    expect(fn () => resolve(Acknowledge::class)->handle($portal, $document->id, '198.51.100.1'))->toThrow(ValidationException::class);

    $request = Acknowledgement::query()->sole();
    expect($request->ip)->toBe('203.0.113.9')->and(Acknowledgement::query()->count())->toBe(1);
});

it('does not let a person acknowledge what was not asked of them', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    $outsider = staffer($this->other);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $portal = resolve(OpenPortal::class)->handle(portalOf($outsider));

    expect(fn () => resolve(Acknowledge::class)->handle($portal, $document->id, null))->toThrow(ModelNotFoundException::class)
        ->and(Acknowledgement::query()->whereNotNull('acknowledged_at')->count())->toBe(0)
        ->and($ana->id)->not->toBe($outsider->id);
});

it('logs telework.acknowledged with ids and channel, never the address', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $portal = resolve(OpenPortal::class)->handle(portalOf($ana));
    Log::spy();

    resolve(Acknowledge::class)->handle($portal, $document->id, '203.0.113.9');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'telework.acknowledged'
        && $context === ['document_id' => $document->id, 'employee_id' => $ana->id, 'channel' => 'portal'])->once();
});

it('lists what is pending in the portal, newest versions only, and shows it gone once confirmed', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    publishDocument(DocumentKind::Policy, $this->center);
    [$latest] = publishDocument(DocumentKind::Policy, $this->center);
    [$risk] = publishDocument(DocumentKind::RiskInformation, $this->center);
    $token = portalOf($ana);

    $this->get(route('portal.show', $token))->assertInertia(fn (Assert $page) => $page
        ->has('pending.documents', 1)
        ->where('pending.documents.0.id', $latest->id)
        ->where('pending.documents.0.title', 'Política de Teletrabajo · versión 2')
        ->where('pending.documents.0.publishedOn', now()->setTimezone(config()->string('app.display_timezone'))->toDateString()));

    $this->post(route('portal.documents.acknowledge', [$token, $latest->id]))->assertRedirect(route('portal.show', $token));

    $this->get(route('portal.show', $token))->assertInertia(fn (Assert $page) => $page->has('pending.documents', 0));
    expect($risk->id)->not->toBe($latest->id);
});

it('keeps the documents of another person out of a portal', function (): void {
    Queue::fake();
    staffer($this->center);
    $outsider = staffer($this->other);
    publishDocument(DocumentKind::Policy, $this->center);

    $this->get(route('portal.show', portalOf($outsider)))->assertInertia(fn (Assert $page) => $page->has('pending.documents', 0));
});

it('serves the PDF through the portal only to the person it was asked of', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    $outsider = staffer($this->other);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $token = portalOf($ana);

    $response = $this->get(route('portal.documents.file', [$token, $document->id]))->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->streamedContent())->toStartWith('%PDF');
    $this->get(route('portal.documents.file', [portalOf($outsider), $document->id]))->assertNotFound();
    $this->get(route('portal.documents.file', ['nope', $document->id]))->assertNotFound();
    $this->get(route('portal.documents.file', [$token, 'not-a-uuid']))->assertNotFound();
});

it('answers 404 when a dead link tries to acknowledge, and shows a validation error on a second try', function (): void {
    Queue::fake();
    $ana = staffer($this->center);
    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    $token = portalOf($ana);

    $this->post(route('portal.documents.acknowledge', ['dead', $document->id]))->assertNotFound();
    $this->post(route('portal.documents.acknowledge', [$token, $document->id]))->assertSessionHasNoErrors();
    $this->post(route('portal.documents.acknowledge', [$token, $document->id]))->assertSessionHasErrors('document');
});

it('publishes without making the requests inline: a queued listener does it after the commit', function (): void {
    Queue::fake();
    staffer($this->center);

    [$document] = publishDocument(DocumentKind::Policy, $this->center);
    Acknowledgement::query()->delete();
    Queue::assertPushed(Illuminate\Events\CallQueuedListener::class, fn (Illuminate\Events\CallQueuedListener $job): bool => $job->class === RequestOnPublication::class);
    expect(is_subclass_of(RequestOnPublication::class, ShouldQueueAfterCommit::class))->toBeTrue()
        ->and(Acknowledgement::query()->count())->toBe(0);

    resolve(RequestOnPublication::class)->handle(new App\Modules\Telework\Events\DocumentPublished($this->tenant->id, [$document->id]));
    expect(Acknowledgement::query()->where('document_id', $document->id)->count())->toBe(1);
});

it('asks a big center with the same queries as a small one, counting only by chunks', function (): void {
    Queue::fake();
    $area = Database\Factories\Organization\AreaFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $count = function (int $people, WorkCenter $center) use ($area): int {
        EmployeeFactory::new()->count($people)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'area_id' => $area->id]);
        $document = TeleworkDocument::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'kind' => DocumentKind::Policy]);
        Illuminate\Support\Facades\DB::enableQueryLog();
        resolve(RequestAcknowledgements::class)->handle([$document->id]);
        $queries = count(Illuminate\Support\Facades\DB::getQueryLog());
        Illuminate\Support\Facades\DB::flushQueryLog();
        Illuminate\Support\Facades\DB::disableQueryLog();

        return $queries;
    };

    $small = $count(20, $this->other);
    $big = $count(400, $this->center);

    expect($big)->toBe($small)
        ->and(Acknowledgement::query()->count())->toBe(420);
});

it('waits two minutes and keeps one waiting email per person, listing everything pending', function (): void {
    Notification::fake();
    Queue::fake();
    $ana = staffer($this->center, ['name' => 'Ana Ruiz']);
    publishDocument(DocumentKind::Policy, $this->center);
    publishDocument(DocumentKind::Policy, $this->center);

    Queue::assertPushed(SendAcknowledgementRequest::class, fn (SendAcknowledgementRequest $job): bool => $job->delay === 120);
    $job = new SendAcknowledgementRequest($this->tenant->id, $ana->id, null);
    expect($job)->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueFor)->toBe(600)
        ->and($job->uniqueId())->toBe("{$this->tenant->id}:{$ana->id}")
        ->and((new SendAcknowledgementRequest($this->tenant->id, null, 7))->uniqueId())->toBe("{$this->tenant->id}:user-7");

    app()->call($job->handle(...));

    Notification::assertSentOnDemand(DocumentsToConfirm::class, function (DocumentsToConfirm $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        $lines = collect($notification->toMail($notifiable)->introLines);

        // The first version was superseded: only the one in force is listed.
        return $lines->contains('- Política de Teletrabajo · versión 2') && ! $lines->contains('- Política de Teletrabajo · versión 1');
    });
});

it('writes the Comisión email in the panel voice, without the personal link sentence', function (): void {
    $member = (new DocumentsToConfirm('https://x.test/docs', 'Cora', 'Acme', ['Información sobre riesgos del Teletrabajo · versión 1'], false))->toMail(new AnonymousNotifiable);
    $person = (new DocumentsToConfirm('https://x.test/portal/t', 'Ana', 'Acme', ['Política de Teletrabajo · versión 1'], true))->toMail(new AnonymousNotifiable);

    expect($member->subject)->toBe('Tienes documentos por confirmar')
        ->and(implode(' ', $member->introLines))->toContain('debes leer y confirmar en el panel')
        ->and($member->outroLines)->toBe([])
        ->and($person->subject)->toBe('Tiene documentos por confirmar')
        ->and(implode(' ', $person->outroLines))->toContain('no lo comparta');
});
