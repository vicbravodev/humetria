<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\Consent;
use App\Modules\Telework\Models\DigestDelivery;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\EvidencePhoto;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\PortalLink;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TeleworkSettings;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Telework\AcknowledgementFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\CommitteeAssignmentFactory;
use Database\Factories\Telework\DigestDeliveryFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\EvidenceRequestFactory;
use Database\Factories\Telework\PortalLinkFactory;
use Database\Factories\Telework\SafetyCommitteeFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TrainingFactory;
use Database\Factories\Telework\VisitAuthorizationFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Company A must never read or touch company B's NOM-037 rows: not by a query (the tenant scope) and not by URL (a 404,
 * never a 403 that would tell A the row exists). Every Telework model has a row in both companies and an entry in
 * isolationRoutes(); a new model without them fails the first test.
 */
const PORTAL_TOKEN = 'isolation-portal-token-of-company-a';

/**
 * Everything NOM-037 keeps about one company, one row of each model, linked the way the module links them.
 *
 * @return array<class-string<Model>, Model>
 */
function isolationWorld(Tenant $tenant): array
{
    workIn($tenant);
    $tenantId = ['tenant_id' => $tenant->id];
    $person = TeleworkerFactory::new()->active()->create($tenantId);
    $workplace = WorkplaceFactory::new()->create([...$tenantId, 'teleworker_id' => $person->id]);
    $checklist = ChecklistFactory::new()->published()->create([...$tenantId, 'work_center_id' => $person->work_center_id]);
    $question = addQuestion($checklist, 1, 'yes');
    $application = ChecklistApplicationFactory::new()->submitted()->create([...$tenantId, 'workplace_id' => $workplace->id, 'checklist_id' => $checklist->id]);
    $request = EvidenceRequestFactory::new()->create([...$tenantId, 'workplace_id' => $workplace->id]);
    $document = TeleworkDocumentFactory::new()->create([...$tenantId, 'work_center_id' => $person->work_center_id]);
    $training = TrainingFactory::new()->create([...$tenantId, 'work_center_id' => $person->work_center_id]);

    return [
        Acknowledgement::class => AcknowledgementFactory::new()->create([...$tenantId, 'document_id' => $document->id, 'employee_id' => $person->employee_id]),
        Checklist::class => $checklist,
        ChecklistAnswer::class => ChecklistAnswer::query()->create([...$tenantId, 'application_id' => $application->id, 'question_id' => $question->id, 'answer' => 'no', 'risk_note' => 'Nota']),
        ChecklistApplication::class => $application,
        ChecklistPhoto::class => ChecklistPhoto::query()->create([...$tenantId, 'application_id' => $application->id, 'question_id' => null, 'path' => 'checklists/'.Str::uuid().'.jpg', 'uploaded_at' => now()]),
        ChecklistQuestion::class => $question,
        CommitteeAssignment::class => CommitteeAssignmentFactory::new()->create([...$tenantId, 'work_center_id' => $person->work_center_id]),
        Consent::class => Consent::query()->create([...$tenantId, 'version' => 1, 'text' => 'Consentimiento', 'created_by' => User::factory()->create()->id]),
        DigestDelivery::class => DigestDeliveryFactory::new()->create($tenantId),
        EquipmentItem::class => EquipmentItemFactory::new()->create([...$tenantId, 'teleworker_id' => $person->id]),
        EvidencePhoto::class => EvidencePhoto::query()->create([...$tenantId, 'evidence_request_id' => $request->id, 'path' => 'evidence/'.Str::uuid().'.jpg', 'uploaded_at' => now()]),
        EvidenceRequest::class => $request,
        PortalLink::class => PortalLinkFactory::new()->token($tenant->slug === 'alpha' ? PORTAL_TOKEN : Str::random(40))->create([...$tenantId, 'employee_id' => $person->employee_id]),
        SafetyCommittee::class => SafetyCommitteeFactory::new()->create([...$tenantId, 'work_center_id' => $person->work_center_id]),
        TeleworkDocument::class => $document,
        Teleworker::class => $person,
        TeleworkSettings::class => TeleworkSettings::query()->create([...$tenantId, 'review_period_months' => 12]),
        Training::class => $training,
        TrainingAttendee::class => TrainingAttendee::query()->create([...$tenantId, 'training_id' => $training->id, 'employee_id' => $person->employee_id]),
        VisitAuthorization::class => VisitAuthorizationFactory::new()->create([...$tenantId, 'workplace_id' => $workplace->id]),
        WorkerNotice::class => WorkerNoticeFactory::new()->create([...$tenantId, 'teleworker_id' => $person->id]),
        Workplace::class => $workplace,
        WorkplaceReview::class => WorkplaceReviewFactory::new()->create([...$tenantId, 'workplace_id' => $workplace->id]),
    ];
}

/** @return list<class-string<Model>> */
function teleworkModels(): array
{
    $models = array_map(
        fn (string $file): string => 'App\\Modules\\Telework\\Models\\'.basename($file, '.php'),
        glob(__DIR__.'/../../../app/Modules/Telework/Models/*.php') ?: [],
    );
    sort($models);

    return $models;
}

beforeEach(function (): void {
    $this->alpha = Tenant::factory()->create(['slug' => 'alpha']);
    $this->beta = Tenant::factory()->create(['slug' => 'beta']);
    $this->betaWorld = isolationWorld($this->beta);
    $this->alphaWorld = isolationWorld($this->alpha);
    workIn($this->alpha);
    $this->owner = memberOf($this->alpha, TenantRole::Owner);
});

// PHPUnit keeps every test object until the run ends: drop the two worlds so 48 tests do not pile them up in memory.
afterEach(function (): void {
    unset($this->alphaWorld, $this->betaWorld, $this->owner);
});

it('has a row of every Telework model in both companies', function (): void {
    expect(array_keys($this->alphaWorld))->toEqualCanonicalizing(teleworkModels())
        ->and(array_keys($this->betaWorld))->toEqualCanonicalizing(teleworkModels());
});

it('does not let company A read a row of company B by query', function (string $model): void {
    $theirs = $this->betaWorld[$model];
    $mine = $this->alphaWorld[$model];

    expect($model::query()->find($theirs->getKey()))->toBeNull()
        ->and($model::query()->whereKey($theirs->getKey())->exists())->toBeFalse()
        ->and($model::query()->find($mine->getKey()))->not->toBeNull()
        ->and($model::query()->count())->toBe(1)
        ->and($model::query()->withoutGlobalScopes()->whereKey($theirs->getKey())->exists())->toBeTrue();
})->with(fn (): array => array_combine(array_map(class_basename(...), teleworkModels()), array_map(fn (string $model): array => [$model], teleworkModels())));

/**
 * The URLs that take the id of each model, with the company-A session (the owner) or the portal link of A.
 * A model with no route of its own is reached through its parent and says so.
 *
 * @param  array<class-string<Model>, Model>  $b
 * @return array<class-string<Model>, list<array{0: string, 1: string, 2?: array<string, mixed>, 3?: array<string, mixed>}>> method, URL, a valid payload and files: a form is validated before the row is looked up
 */
function isolationRoutes(Tenant $tenant, array $b, int $reviewer): array
{
    $center = $b[Teleworker::class]->work_center_id;
    $panel = fn (string $name, mixed ...$parameters): string => route("tenant.telework.{$name}", [$tenant, ...$parameters], absolute: false);
    $address = ['street' => 'Calle Hidalgo', 'exterior_number' => '1', 'neighborhood' => 'Centro', 'postal_code' => '76000', 'municipality' => 'Querétaro', 'state' => 'QUE'];
    $decision = ['decision' => 'valid'];
    $portal = fn (string $name, mixed ...$parameters): string => route("portal.{$name}", [PORTAL_TOKEN, ...$parameters], absolute: false);

    return [
        Acknowledgement::class => [['GET', $panel('documents.acknowledgements.index', $b[TeleworkDocument::class]->id)]],
        Checklist::class => [['GET', $panel('checklist.show', $center)], ['POST', $panel('checklist.publish', $center)], ['POST', $panel('checklist.draft', $center)]],
        ChecklistAnswer::class => [['GET', $panel('reviews.show', $b[ChecklistApplication::class]->id)]],
        ChecklistApplication::class => [
            ['GET', $panel('reviews.show', $b[ChecklistApplication::class]->id)],
            ['POST', $panel('reviews.store', $b[ChecklistApplication::class]->id), $decision],
            ['GET', $portal('checklist.show', $b[ChecklistApplication::class]->id)],
            ['POST', $portal('checklist.store', $b[ChecklistApplication::class]->id), ['answers' => [Str::uuid()->toString() => 'yes']]],
        ],
        ChecklistPhoto::class => [['GET', $panel('photos.show', $b[ChecklistPhoto::class]->id)]],
        ChecklistQuestion::class => [
            ['PUT', $panel('checklist.questions.update', $center, $b[ChecklistQuestion::class]->id), ['text' => 'Texto', 'favorable' => 'yes']],
            ['DELETE', $panel('checklist.questions.destroy', $center, $b[ChecklistQuestion::class]->id)],
            ['POST', $panel('checklist.questions.move', $center, $b[ChecklistQuestion::class]->id)],
        ],
        CommitteeAssignment::class => [['POST', $panel('committee.members.store', $center), ['user_id' => $reviewer]]],
        Consent::class => [],
        DigestDelivery::class => [],
        EquipmentItem::class => [['POST', $portal('equipment.respond', $b[EquipmentItem::class]->id), ['received' => true]]],
        EvidencePhoto::class => [['GET', $panel('evidence-photos.show', $b[EvidencePhoto::class]->id)]],
        EvidenceRequest::class => [['POST', $portal('evidence.store', $b[EvidenceRequest::class]->id), [], ['photo_general_0' => UploadedFile::fake()->image('a.jpg')]]],
        PortalLink::class => [],
        SafetyCommittee::class => [['GET', $panel('committee.show', $center)], ['GET', $panel('committee.act', $center)]],
        TeleworkDocument::class => [
            ['GET', $panel('documents.file', $b[TeleworkDocument::class]->id)],
            ['POST', $panel('documents.acknowledgements.store', $b[TeleworkDocument::class]->id), ['employee_ids' => [Str::uuid()->toString()], 'date' => now()->toDateString(), 'note' => 'Firmado en papel']],
            ['POST', $panel('documents.acknowledge', $b[TeleworkDocument::class]->id)],
            ['GET', $portal('documents.file', $b[TeleworkDocument::class]->id)],
            ['POST', $portal('documents.acknowledge', $b[TeleworkDocument::class]->id)],
        ],
        Teleworker::class => [
            ['GET', $panel('teleworkers.show', $b[Teleworker::class]->id)],
            ['POST', $panel('teleworkers.link', $b[Teleworker::class]->id)],
            ['POST', $panel('teleworkers.workplaces.store', $b[Teleworker::class]->id), $address],
            ['POST', $panel('teleworkers.equipment.store', $b[Teleworker::class]->id), ['kind' => 'chair', 'description' => 'Silla', 'delivered_on' => now()->toDateString()]],
            ['POST', $panel('teleworkers.join', $b[Teleworker::class]->id), ['marital_status' => 'single', 'activities' => 'Captura', 'contact_phone' => '4421234567', ...$address]],
            ['POST', $panel('teleworkers.end', $b[Teleworker::class]->id), ['reason' => 'other', 'ended_on' => now()->toDateString()]],
        ],
        TeleworkSettings::class => [],
        Training::class => [['GET', $panel('trainings.evidence', $b[Training::class]->id)]],
        TrainingAttendee::class => [],
        VisitAuthorization::class => [
            ['POST', $panel('visit-authorizations.document.store', $b[VisitAuthorization::class]->id), [], ['document' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]],
            ['GET', $panel('visit-authorizations.document.show', $b[VisitAuthorization::class]->id)],
            ['POST', $panel('visit-authorizations.cancel', $b[VisitAuthorization::class]->id)],
            ['POST', $portal('visits.confirm', $b[VisitAuthorization::class]->id)],
        ],
        WorkerNotice::class => [['POST', $panel('notices.review', $b[WorkerNotice::class]->id), ['note' => 'Revisado']]],
        Workplace::class => [
            ['POST', $panel('workplaces.retire', $b[Workplace::class]->id)],
            ['POST', $panel('workplaces.remote-check.store', $b[Workplace::class]->id), $decision],
            ['POST', $panel('evidence.store', $b[Workplace::class]->id), ['kind' => 'photos']],
            ['POST', $panel('visit-authorizations.store', $b[Workplace::class]->id)],
            ['POST', $panel('workplaces.visit.store', $b[Workplace::class]->id), [...$decision, 'visit_authorization_id' => Str::uuid()->toString(), 'visited_on' => now()->toDateString()]],
        ],
        WorkplaceReview::class => [],
    ];
}

it('lists the routes of every Telework model', function (): void {
    expect(array_keys(isolationRoutes($this->alpha, $this->betaWorld, $this->owner->id)))->toEqualCanonicalizing(teleworkModels());
});

it('answers 404, never 403, to the id of a row of another company', function (string $model): void {
    $routes = isolationRoutes($this->alpha, $this->betaWorld, $this->owner->id)[$model];

    expect($routes)->toBeArray();

    foreach ($routes as $route) {
        [$method, $url] = $route;
        $this->actingAs($this->owner)->call($method, $url, $route[2] ?? [], [], $route[3] ?? [])->assertNotFound("{$method} {$url}");
    }
})->with(fn (): array => array_combine(array_map(class_basename(...), teleworkModels()), array_map(fn (string $model): array => [$model], teleworkModels())));
