<?php

declare(strict_types=1);

use App\Modules\Telework\Actions\AttachVisitDocument;
use App\Modules\Telework\Actions\CancelVisitAuthorization;
use App\Modules\Telework\Actions\ConfirmVisitAuthorization;
use App\Modules\Telework\Actions\FulfillPhotoRequest;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Actions\RecordVisit;
use App\Modules\Telework\Actions\RequestEvidence;
use App\Modules\Telework\Actions\RequestVisitAuthorization;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\ReviewMethod;
use App\Modules\Telework\Http\Controllers\EvidenceController;
use App\Modules\Telework\Http\Controllers\PortalEvidenceController;
use App\Modules\Telework\Http\Controllers\VisitController;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EvidencePhoto;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

mutates(CancelVisitAuthorization::class, RequestEvidence::class, FulfillPhotoRequest::class, RequestVisitAuthorization::class, ConfirmVisitAuthorization::class, AttachVisitDocument::class, RecordVisit::class, EvidenceController::class, VisitController::class, PortalEvidenceController::class);

beforeEach(function (): void {
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    $this->center = WorkCenter::query()->findOrFail($this->employee->work_center_id);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
    $teleworker = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    $this->workplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $teleworker->id]);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
});

function evidenceUrl(): string
{
    return route('tenant.telework.evidence.store', [test()->tenant, test()->workplace->id]);
}

/** @return array<string, mixed> The props of the portal page. */
function evidencePending(): array
{
    $response = test()->get(route('portal.show', test()->token));
    $response->assertOk();

    return $response->viewData('page')['props']['pending'];
}

function evidencePng(string $name = 'lugar.png'): UploadedFile
{
    return new UploadedFile(__DIR__.'/../../Fixtures/photo.png', $name, null, null, true);
}

/** @return array<string, string> The messages of the validation error an attempt raises, by field. */
function evidenceRefused(Closure $attempt): array
{
    try {
        $attempt();
    } catch (ValidationException $e) {
        return array_map(fn (array $messages): string => $messages[0], $e->errors());
    }

    return [];
}

it('creates an evidence request that appears in the portal', function (): void {
    // Only the request is under test: the reminder job it queues does not run.
    Illuminate\Support\Facades\Queue::fake();
    $this->actingAs($this->cora)->post(evidenceUrl(), ['kind' => 'photos', 'note' => 'Una foto de la silla.'])->assertSessionHasNoErrors()->assertRedirect();

    $request = EvidenceRequest::query()->sole();
    expect($request->kind)->toBe(EvidenceKind::Photos)->and($request->fulfilled_at)->toBeNull()->and($request->requested_by)->toBe($this->cora->id);

    $pending = evidencePending();
    expect($pending['photos'])->toHaveCount(1)
        ->and($pending['photos'][0])->toBe(['id' => $request->id, 'workplace' => 'Calle Hidalgo 45, Centro, Querétaro, Querétaro', 'note' => 'Una foto de la silla.']);
});

it('does not show the person a remote check, which only the Comisión does', function (): void {
    $this->actingAs($this->cora)->post(evidenceUrl(), ['kind' => 'remote_check', 'note' => 'Videollamada el lunes.'])->assertSessionHasNoErrors();

    expect(EvidenceRequest::query()->sole()->kind)->toBe(EvidenceKind::RemoteCheck)->and(evidencePending()['photos'])->toBe([]);
});

it('asks for evidence only with a kind and a note, only for a workplace still in use, and only if assigned', function (): void {
    $this->actingAs($this->cora);
    $this->post(evidenceUrl(), ['kind' => 'photos'])->assertSessionHasErrors('note');
    $this->post(evidenceUrl(), ['kind' => 'documents', 'note' => 'x'])->assertSessionHasErrors('kind');

    $this->actingAs($this->hr)->post(evidenceUrl(), ['kind' => 'photos', 'note' => 'x'])->assertForbidden();
    $this->actingAs($this->viewer)->post(evidenceUrl(), ['kind' => 'photos', 'note' => 'x'])->assertNotFound();
    $this->actingAs($this->cora)->post(route('tenant.telework.evidence.store', [$this->tenant, 'nope']), ['kind' => 'photos', 'note' => 'x'])->assertNotFound();

    $this->workplace->update(['retired_at' => now()]);
    $this->actingAs($this->cora)->post(evidenceUrl(), ['kind' => 'photos', 'note' => 'x'])->assertSessionHasErrors('workplace');

    expect(EvidenceRequest::query()->count())->toBe(0);
});

it('logs the request with ids and the kind, never the note', function (): void {
    Log::spy();

    $request = resolve(RequestEvidence::class)->handle($this->workplace, EvidenceKind::Photos, 'Nota privada', $this->cora->id);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.evidence_requested'
        && $context === ['evidence_request_id' => $request->id, 'workplace_id' => $this->workplace->id, 'kind' => 'photos']
        && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'privada'))->once();
});

it('lets the person send the photos asked for through the portal, once', function (): void {
    $request = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Log::spy();

    $this->post(route('portal.evidence.store', [$this->token, $request->id]), ['photo_general_0' => evidencePng(), 'photo_general_1' => evidencePng('otra.png')])->assertSessionHasNoErrors()->assertRedirect(route('portal.show', $this->token));

    $stored = EvidencePhoto::query()->get();
    expect($stored)->toHaveCount(2)
        ->and($request->refresh()->fulfilled_at)->not->toBeNull()
        ->and(evidencePending()['photos'])->toBe([]);

    foreach ($stored as $photo) {
        Storage::disk('telework_files')->assertExists($photo->path);
        expect(str_starts_with($photo->path, "evidence/{$this->tenant->id}/{$request->id}/"))->toBeTrue();
    }

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.evidence_fulfilled'
        && $context === ['evidence_request_id' => $request->id, 'workplace_id' => $this->workplace->id, 'kind' => 'photos', 'photo_count' => 2])->once();

    $this->post(route('portal.evidence.store', [$this->token, $request->id]), ['photo_general_0' => evidencePng()])->assertSessionHasErrors('evidence');
    expect(EvidencePhoto::query()->count())->toBe(2);
});

it('refuses photos that are not images, none at all, and more than the cap, leaving nothing behind', function (): void {
    $request = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $url = route('portal.evidence.store', [$this->token, $request->id]);
    $bad = UploadedFile::fake()->createWithContent('foto.jpg', '%PDF-1.4 not an image');

    $this->post($url, [])->assertSessionHasErrors('photos');
    $this->post($url, ['photo_general_0' => evidencePng(), 'photo_general_1' => $bad])->assertSessionHasErrors('photos');

    $most = config()->integer('telework.max_photos_per_group');
    $many = [];
    foreach (range(0, $most) as $n) {
        $many["photo_general_{$n}"] = evidencePng("{$n}.png");
    }
    $this->post($url, $many)->assertSessionHasErrors('photos');

    expect(EvidencePhoto::query()->count())->toBe(0)->and($request->refresh()->fulfilled_at)->toBeNull()
        ->and(Storage::disk('telework_files')->allFiles())->toBe([]);
});

it('keeps the photo requests of other people, other kinds and retired workplaces out of reach', function (): void {
    $otherEmployee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $otherWorkplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $otherEmployee->id])->id]);
    $theirs = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $otherWorkplace->id]);
    $remote = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'kind' => EvidenceKind::RemoteCheck]);
    $retired = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id, 'retired_at' => now()]);
    $old = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $retired->id]);

    foreach ([$theirs, $remote, $old] as $request) {
        $this->post(route('portal.evidence.store', [$this->token, $request->id]), ['photo_general_0' => evidencePng()])->assertNotFound();
    }

    $this->post(route('portal.evidence.store', ['not-a-link', $remote->id]), ['photo_general_0' => evidencePng()])->assertNotFound();
    expect(EvidencePhoto::query()->count())->toBe(0)->and(evidencePending()['photos'])->toBe([]);
});

it('fulfils a remote-check request when the Comisión records the remote check', function (): void {
    $asked = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'kind' => EvidenceKind::RemoteCheck]);
    $photos = EvidenceRequestFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    // The person answered the list: a remote check validates only then.
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Log::spy();

    $this->actingAs($this->cora)->post(route('tenant.telework.workplaces.remote-check.store', [$this->tenant, $this->workplace->id]), ['decision' => 'valid'])->assertSessionHasNoErrors();

    expect($asked->refresh()->fulfilled_at)->not->toBeNull()->and($photos->refresh()->fulfilled_at)->toBeNull();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.evidence_fulfilled'
        && $context === ['evidence_request_id' => $asked->id, 'workplace_id' => $this->workplace->id, 'kind' => 'remote_check'])->once();
});

it('asks the person to authorize a visit and shows it in their portal until they confirm', function (): void {
    Illuminate\Support\Facades\Queue::fake();
    Log::spy();
    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertSessionHasNoErrors()->assertRedirect();

    $authorization = VisitAuthorization::query()->sole();
    expect($authorization->authorized())->toBeFalse()
        ->and(evidencePending()['visits'])->toBe([['id' => $authorization->id, 'workplace' => 'Calle Hidalgo 45, Centro, Querétaro, Querétaro']]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.visit_authorization_requested'
        && $context === ['visit_authorization_id' => $authorization->id, 'workplace_id' => $this->workplace->id])->once();

    // Asking again while one waits does not pile up requests.
    $this->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertSessionHasErrors('visit');
    expect(VisitAuthorization::query()->count())->toBe(1);
});

it('records the confirmation of the person with its time and address', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Log::spy();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post(route('portal.visits.confirm', [$this->token, $authorization->id]))->assertSessionHasNoErrors()->assertRedirect(route('portal.show', $this->token));

    $authorization->refresh();
    expect($authorization->confirmed_at?->toIso8601String())->toBe('2026-03-10T18:00:00+00:00')
        ->and($authorization->confirmed_ip)->toBe('203.0.113.9')
        ->and($authorization->authorized())->toBeTrue()
        ->and(evidencePending()['visits'])->toBe([]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.visit_authorization_confirmed'
        && $context === ['visit_authorization_id' => $authorization->id, 'workplace_id' => $this->workplace->id])->once();

    $this->post(route('portal.visits.confirm', [$this->token, $authorization->id]))->assertSessionHasErrors('visit');
});

it('does not let a person confirm the visit of someone else\'s workplace', function (): void {
    $otherEmployee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $otherWorkplace = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $otherEmployee->id])->id]);
    $theirs = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $otherWorkplace->id]);

    $this->post(route('portal.visits.confirm', [$this->token, $theirs->id]))->assertNotFound();

    expect($theirs->refresh()->confirmed_at)->toBeNull();
});

it('lets RH or the assigned Comisión attach the signed paper authorization as a PDF', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $url = route('tenant.telework.visit-authorizations.document.store', [$this->tenant, $authorization->id]);
    // Real uploads (the fake ones take their type from the extension): the server sniffs the content.
    $file = function (string $content): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'vis');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'autorizacion.pdf', null, null, true);
    };
    $pdf = fn () => $file("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

    $this->actingAs($this->viewer)->post($url, ['document' => $pdf()])->assertForbidden();
    $stranger = memberOf($this->tenant, TenantRole::Committee);
    $this->actingAs($stranger)->post($url, ['document' => $pdf()])->assertNotFound();
    $this->actingAs($this->hr)->post($url, ['document' => $file('not a pdf')])->assertSessionHasErrors('document');
    $this->actingAs($this->hr)->post($url, [])->assertSessionHasErrors('document');
    expect($authorization->refresh()->authorized())->toBeFalse();

    $this->actingAs($this->hr)->post($url, ['document' => $pdf()])->assertSessionHasNoErrors()->assertRedirect();

    $authorization->refresh();
    expect($authorization->authorized())->toBeTrue()->and($authorization->confirmed_at)->toBeNull();
    Storage::disk('telework_files')->assertExists((string) $authorization->document_path);
    expect(evidencePending()['visits'])->toBe([]);
});

it('records a visit with the person\'s written authorization', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $authorization = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    // The person answered the list: a visit validates only then.
    $list = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Log::spy();

    $this->actingAs($this->cora)->post(route('tenant.telework.workplaces.visit.store', [$this->tenant, $this->workplace->id]), [
        'visit_authorization_id' => $authorization->id,
        'visited_on' => '2026-03-09',
        'decision' => 'valid_with_plan',
        'notes' => 'Se revisó en persona.',
        'action_plan' => 'Poner un reposapiés.',
        'plan_due_on' => '2026-04-01',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $review = WorkplaceReview::query()->sole();
    expect($review->method)->toBe(ReviewMethod::Visit)
        ->and($review->decision)->toBe(ReviewDecision::ValidWithPlan)
        ->and($review->visit_authorization_id)->toBe($authorization->id)
        ->and($review->visited_on?->toDateString())->toBe('2026-03-09')
        // The list the person sent is the one this visit decided: it leaves the inbox.
        ->and($review->application_id)->toBe($list->id);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.visit_recorded'
        && $context === ['review_id' => $review->id, 'workplace_id' => $this->workplace->id, 'visit_authorization_id' => $authorization->id])->once();
});

it('does not record a visit without a given, unused authorization of that workplace, nor one in the future', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $waiting = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $other = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);
    $foreign = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $other->id]);
    $given = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $record = fn (VisitAuthorization $authorization, string $on) => resolve(RecordVisit::class)->handle($this->workplace, $authorization, CarbonImmutable::parse($on), ReviewDecision::Valid, '', null, null, $this->cora->id);

    expect(evidenceRefused(fn () => $record($waiting, '2026-03-09')))->toHaveKey('visit_authorization_id')
        ->and(evidenceRefused(fn () => $record($foreign, '2026-03-09')))->toHaveKey('visit_authorization_id')
        ->and(evidenceRefused(fn () => $record($given, '2026-03-11')))->toHaveKey('visited_on')
        ->and(WorkplaceReview::query()->count())->toBe(0);

    // Today counts, and an authorization covers one visit.
    expect($record($given, '2026-03-10')->method)->toBe(ReviewMethod::Visit)
        ->and(evidenceRefused(fn () => $record($given, '2026-03-09')))->toHaveKey('visit_authorization_id');
});

it('keeps recording a visit behind the same gate and its fields required', function (): void {
    $authorization = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $url = route('tenant.telework.workplaces.visit.store', [$this->tenant, $this->workplace->id]);
    $form = ['visit_authorization_id' => $authorization->id, 'visited_on' => '2026-01-01', 'decision' => 'valid'];

    $this->actingAs($this->hr)->post($url, $form)->assertForbidden();
    $this->actingAs($this->viewer)->post($url, $form)->assertNotFound();
    $this->actingAs($this->cora)->post($url, [...$form, 'visited_on' => null])->assertSessionHasErrors('visited_on');
    $this->actingAs($this->cora)->post($url, [...$form, 'visit_authorization_id' => 'nope'])->assertSessionHasErrors('visit_authorization_id');
    $this->actingAs($this->cora)->post($url, [...$form, 'visited_on' => 'yesterday'])->assertSessionHasErrors('visited_on');

    expect(WorkplaceReview::query()->count())->toBe(0);
});

it('shows a reviewed checklist as Revisada and never the decision', function (): void {
    $waiting = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $other = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id, 'street' => 'Calle Juárez'])->id]);

    $this->actingAs($this->cora)->post(route('tenant.telework.reviews.store', [$this->tenant, $other->id]), ['decision' => 'not_valid', 'notes' => 'No cumple.'])->assertSessionHasNoErrors();

    $sent = collect(evidencePending()['sent'])->pluck('label', 'id');
    expect($sent[$waiting->id])->toBe('En revisión de la Comisión')
        ->and($sent[$other->id])->toBe('Revisada')
        ->and(json_encode(evidencePending(), JSON_THROW_ON_ERROR))->not->toContain('not_valid')->not->toContain('No validado')->not->toContain('No cumple');
});

it('keeps the other company\'s evidence out of the portal and the panel', function (): void {
    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $spy = memberOf($rival, TenantRole::Committee);

    $this->actingAs($spy)->post(route('tenant.telework.evidence.store', ['rival', $this->workplace->id]), ['kind' => 'photos', 'note' => 'x'])->assertNotFound();
    $this->actingAs($spy)->post(route('tenant.telework.visit-authorizations.store', ['rival', $this->workplace->id]))->assertNotFound();

    workIn($this->tenant);
    expect(EvidenceRequest::query()->count())->toBe(0)->and(VisitAuthorization::query()->count())->toBe(0);
});

it('keeps a confirmation whose address is unknown, with the address empty', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $portal = resolve(App\Modules\Telework\Actions\OpenPortal::class)->handle($this->token);

    resolve(ConfirmVisitAuthorization::class)->handle($portal, $authorization->id, null);

    $authorization->refresh();
    expect($authorization->confirmed_at)->not->toBeNull()->and($authorization->confirmed_ip)->toBeNull()->and($authorization->authorized())->toBeTrue();
});

it('tells who may not act that the page is not theirs before it judges what they sent', function (): void {
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $requests = [
        route('tenant.telework.reviews.store', [$this->tenant, $application->id]),
        route('tenant.telework.workplaces.remote-check.store', [$this->tenant, $this->workplace->id]),
        route('tenant.telework.evidence.store', [$this->tenant, $this->workplace->id]),
        route('tenant.telework.workplaces.visit.store', [$this->tenant, $this->workplace->id]),
        route('tenant.telework.visit-authorizations.document.store', [$this->tenant, $authorization->id]),
    ];

    // An empty form is invalid everywhere: only who may act sees the validation errors.
    foreach ($requests as $url) {
        $this->actingAs($this->viewer)->post($url, [])->assertStatus(evidenceIsDocument($url) ? 403 : 404);
        $this->actingAs($this->hr)->post($url, [])->assertStatus(evidenceIsDocument($url) ? 302 : 403);
    }

    $this->actingAs($this->cora)->post($requests[0], [])->assertSessionHasErrors('decision');
    expect(WorkplaceReview::query()->count())->toBe(0);
});

function evidenceIsDocument(string $url): bool
{
    return str_ends_with($url, '/document');
}

it('cancels a visit request nobody used, which stops blocking a new one and vanishes from the portal', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $cancel = route('tenant.telework.visit-authorizations.cancel', [$this->tenant, $authorization->id]);
    Log::spy();

    $this->actingAs($this->viewer)->post($cancel)->assertForbidden();
    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertSessionHasErrors('visit');

    $this->actingAs($this->cora)->post($cancel)->assertSessionHasNoErrors()->assertRedirect();

    $authorization->refresh();
    expect($authorization->cancelled_at)->not->toBeNull()->and($authorization->cancelled_by)->toBe($this->cora->id)->and($authorization->authorized())->toBeFalse()
        ->and(evidencePending()['visits'])->toBe([]);
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'telework.visit_authorization_cancelled'
        && $context === ['visit_authorization_id' => $authorization->id, 'workplace_id' => $this->workplace->id, 'cancelled_by' => $this->cora->id])->once();

    $this->post($cancel)->assertSessionHasErrors('visit');
    $this->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertSessionHasNoErrors();
    expect(VisitAuthorization::query()->count())->toBe(2);
});

it('does not let a cancelled request be confirmed, signed or used', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'cancelled_at' => now(), 'cancelled_by' => $this->cora->id]);
    $given = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $given->update(['cancelled_at' => now(), 'cancelled_by' => $this->cora->id]);

    $this->post(route('portal.visits.confirm', [$this->token, $authorization->id]))->assertNotFound();
    expect(evidenceRefused(fn () => resolve(AttachVisitDocument::class)->handle($authorization, __FILE__)))->toHaveKey('document')
        ->and(evidenceRefused(fn () => resolve(RecordVisit::class)->handle($this->workplace, $given, CarbonImmutable::now(config()->string('app.display_timezone')), ReviewDecision::Valid, '', null, null, $this->cora->id)))->toHaveKey('visit_authorization_id');
});

it('takes today in Mexico City, not in UTC, as the latest visit date', function (): void {
    $this->travelTo('2026-05-11 00:30:00'); // UTC; still 2026-05-10 in Mexico City
    $given = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $record = fn (string $on) => resolve(RecordVisit::class)->handle($this->workplace, $given, CarbonImmutable::parse($on), ReviewDecision::Valid, '', null, null, $this->cora->id);

    expect(evidenceRefused(fn () => $record('2026-05-11')))->toHaveKey('visited_on')
        ->and($record('2026-05-10')->visited_on?->toDateString())->toBe('2026-05-10');
});

it('does not cancel a request that already backs a visit, nor one outside reach', function (): void {
    $used = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    resolve(RecordVisit::class)->handle($this->workplace, $used, CarbonImmutable::now(config()->string('app.display_timezone')), ReviewDecision::Valid, '', null, null, $this->cora->id);
    $stranger = memberOf($this->tenant, TenantRole::Committee);

    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.cancel', [$this->tenant, $used->id]))->assertSessionHasErrors('visit');
    $this->actingAs($stranger)->post(route('tenant.telework.visit-authorizations.cancel', [$this->tenant, $used->id]))->assertNotFound();
    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.cancel', [$this->tenant, 'nope']))->assertNotFound();
    expect($used->refresh()->cancelled_at)->toBeNull();
});

it('queues a portal reminder for the person when photos are requested or a visit needs authorization (one waiting at a time)', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    resolve(RequestEvidence::class)->handle($this->workplace, EvidenceKind::Photos, 'Una foto de la silla.', $this->cora->id);
    resolve(RequestVisitAuthorization::class)->handle($this->workplace, $this->cora->id);

    Illuminate\Support\Facades\Queue::assertPushed(App\Modules\Telework\Jobs\SendPortalReminder::class, fn ($job): bool => $job->tenantId === $this->tenant->id && $job->employeeId === $this->employee->id && $job->userId === null && $job->delay === 120);
});

it('does not remind the person of a remote check, which the Comisión does itself', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    resolve(RequestEvidence::class)->handle($this->workplace, EvidenceKind::RemoteCheck, 'Revisión remota.', $this->cora->id);

    Illuminate\Support\Facades\Queue::assertNothingPushed();
});

it('does not validate by visit a workplace whose person answered no list, first or after a previous review', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    $visit = function (ReviewDecision $decision, string $notes = '') {
        $authorization = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);

        return resolve(RecordVisit::class)->handle($this->workplace, $authorization, CarbonImmutable::now(), $decision, $notes, null, null, $this->cora->id);
    };

    expect(evidenceRefused(fn () => $visit(ReviewDecision::Valid)))->toBe(['application' => 'Antes de validar, la persona debe contestar la lista de este lugar.'])
        ->and($visit(ReviewDecision::NotValid, 'La silla está rota.')->decision)->toBe(ReviewDecision::NotValid);

    // A list sent before the previous review does not back a new validation.
    ChecklistApplicationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'submitted_at' => '2026-03-10 17:00:00']);
    expect(evidenceRefused(fn () => $visit(ReviewDecision::Valid)))->toHaveKey('application');

    $this->travelTo('2026-03-11 18:00:00');
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id, 'section' => 2]);

    expect($visit(ReviewDecision::Valid)->method)->toBe(ReviewMethod::Visit)
        ->and(VisitAuthorization::query()->count())->toBe(4);
});

it('confirms each step of a visit in a toast', function (): void {
    $this->travelTo('2026-03-10 18:00:00');
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $toast = fn (string $message): array => ['toast' => ['type' => 'success', 'message' => $message]];

    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, $toast('Se pidió la autorización de la visita. La persona la verá en su portal.'));
    $asked = VisitAuthorization::query()->sole();
    $this->post(route('tenant.telework.visit-authorizations.cancel', [$this->tenant, $asked->id]))
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, $toast('Solicitud de visita cancelada.'));

    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $path = tempnam(sys_get_temp_dir(), 'vis');
    file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
    $this->post(route('tenant.telework.visit-authorizations.document.store', [$this->tenant, $authorization->id]), ['document' => new UploadedFile($path, 'autorizacion.pdf', null, null, true)])
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, $toast('Autorización guardada.'));
    $this->post(route('tenant.telework.workplaces.visit.store', [$this->tenant, $this->workplace->id]), ['visit_authorization_id' => $authorization->id, 'visited_on' => '2026-03-09', 'decision' => 'valid'])
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, $toast('Visita registrada.'));
});

it('says a visit needs the written authorization of that workplace, and lets only the assigned Comisión ask for one', function (): void {
    $other = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $this->workplace->teleworker_id]);
    $foreign = VisitAuthorizationFactory::new()->confirmed()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $other->id]);

    $this->actingAs($this->cora)->post(route('tenant.telework.workplaces.visit.store', [$this->tenant, $this->workplace->id]), ['visit_authorization_id' => $foreign->id, 'visited_on' => '2026-01-01', 'decision' => 'valid'])
        ->assertSessionHasErrors(['visit_authorization_id' => 'Se necesita la autorización escrita de la persona.']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertNotFound();
    expect(VisitAuthorization::query()->count())->toBe(1);
});

it('keeps the signed paper under the company and the workplace, logs it with ids, and takes it only once', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    $file = function (): string {
        $path = tempnam(sys_get_temp_dir(), 'vis');
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    };
    Log::spy();

    resolve(AttachVisitDocument::class)->handle($authorization, $file());

    expect($authorization->refresh()->document_path)->toMatch('#^visits/'.$this->tenant->id.'/'.$this->workplace->id.'/authorization-[0-9a-f-]{36}\.pdf$#');
    Log::shouldHaveReceived('info')->with('telework.visit_document_attached', ['visit_authorization_id' => $authorization->id, 'workplace_id' => $this->workplace->id])->once();
    expect(evidenceRefused(fn () => resolve(AttachVisitDocument::class)->handle($authorization, $file())))->toBe(['document' => 'Esta autorización ya tiene su documento.']);
});

it('confirms an evidence request in a toast, and keeps it to the assigned Comisión of a workplace that exists', function (): void {
    Illuminate\Support\Facades\Queue::fake();
    $form = ['kind' => 'photos', 'note' => 'Una foto de la silla.'];

    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->post(evidenceUrl(), $form)->assertNotFound();
    $this->actingAs($this->cora)->post(route('tenant.telework.evidence.store', [$this->tenant, (string) Illuminate\Support\Str::uuid()]), $form)->assertNotFound();
    expect(EvidenceRequest::query()->count())->toBe(0);

    $this->actingAs($this->cora)->post(evidenceUrl(), $form)
        ->assertSessionHas(Inertia\Support\SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Solicitud registrada.']]);
});

it('answers 404 for the paper of a visit authorization that does not exist or whose address is malformed', function (): void {
    foreach (['not-a-uuid', (string) Illuminate\Support\Str::uuid()] as $id) {
        $this->actingAs($this->hr)->post(route('tenant.telework.visit-authorizations.document.store', [$this->tenant, $id]), [])->assertNotFound();
    }
});

it('follows the roles: RH without the review permission is told nothing exists, and the Comisión without view-telework may not read', function (): void {
    $authorization = VisitAuthorizationFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $this->workplace->id]);
    Spatie\Permission\Models\Role::findByName(TenantRole::Hr->value)->revokePermissionTo('review-workplaces');
    Spatie\Permission\Models\Role::findByName(TenantRole::Committee->value)->revokePermissionTo('view-telework');

    $this->actingAs($this->hr)->post(route('tenant.telework.visit-authorizations.store', [$this->tenant, $this->workplace->id]))->assertNotFound();
    $this->actingAs($this->cora)->post(route('tenant.telework.visit-authorizations.cancel', [$this->tenant, $authorization->id]))->assertForbidden();
});
