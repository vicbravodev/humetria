<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Http\Controllers\DossierController;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\Dossier;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\AcknowledgementFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TrainingFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const QUERIES_DOSSIER = 59;

mutates(DossierController::class, Dossier::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'legal_name' => 'Acme Manufactura SA de CV']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function dossierUrl(?WorkCenter $center = null): string
{
    return route('tenant.telework.dossier', [test()->tenant, ($center ?? test()->center)->id]);
}

function dossierPerson(string $name = 'Ana Ruiz'): Teleworker
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'name' => $name, 'job_title' => 'Analista']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employee->id]);
    $place = WorkplaceFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => $person->id]);
    WorkplaceReviewFactory::new()->create(['tenant_id' => test()->tenant->id, 'workplace_id' => $place->id, 'notes' => 'NOTA-PRIVADA', 'action_plan' => 'PLAN-PRIVADO']);

    return $person;
}

/** @return list<string> every key of a nested array, at any depth */
function dossierKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        $keys = [...$keys, (string) $key, ...dossierKeys($child)];
    }

    return $keys;
}

it('lists the 17 rows of table 10.3 in order, each with its state, and Falta for an empty center', function (): void {
    $this->actingAs($this->owner)->get(dossierUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/dossier')
        ->where('header.legalName', 'Acme Manufactura SA de CV')
        ->where('header.workCenter', 'Planta Norte')
        ->where('header.generatedOn', '2026-03-01')
        ->where('header.generatedBy', $this->owner->name)
        ->has('rows', 17)
        ->where('rows', fn ($rows) => collect($rows)->pluck('numeral')->all() === ['5.1', '5.1.1', '5.1.2', '5.2', '5.3', '5.4 y 7', '5.5', '5.5.1', '5.6', '5.7', '5.8', '5.9 y 8', '5.10', '5.11', '5.12', '5.13', '5.14'])
        ->where('rows.3', fn ($row) => $row['state'] === 'missing' && $row['stateLabel'] === 'Falta')
        ->where('roster', [])
        ->where('workplaces', [])
        ->where('checklist', null)
        ->where('documents', [])
        ->where('committee', null));
});

it('shows the evidence of each row', function (): void {
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'version' => 2]);
    SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'constituted_on' => '2026-01-10', 'act_path' => 'committees/x/act.pdf']);
    $ana = dossierPerson();
    $beto = dossierPerson('Beto Soto');
    $beto->update(['started_at' => '2025-01-10 12:00:00']);
    $policy = TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy, 'covered_items' => ['a', 'b']]);
    AcknowledgementFactory::new()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $ana->employee_id]);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Received, 'responded_at' => now(), 'delivered_on' => '2026-02-01']);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Refused, 'refusal_reason' => 'MOTIVO-PRIVADO', 'responded_at' => now(), 'delivered_on' => '2026-02-10']);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'delivered_on' => '2026-02-15']);
    $recent = TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => '2026-02-20', 'topics' => ['hazards', 'ict']]);
    TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => '2025-01-20']);
    TrainingAttendee::query()->create(['tenant_id' => $this->tenant->id, 'training_id' => $recent->id, 'employee_id' => $ana->employee_id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'kind' => NoticeKind::WorkRisk]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $beto->id, 'kind' => NoticeKind::WorkRisk, 'reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'Revisado.']);

    $this->actingAs($this->hr)->get(dossierUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('roster', 2)
        ->where('workplaces.0', fn ($place) => $place['person'] === 'Ana Ruiz' && $place['review']['decidedOn'] === '2026-03-01' && $place['review']['decision'] !== '' && $place['review']['method'] !== '')
        ->where('checklist', fn ($checklist) => $checklist['version'] === 2 && $checklist['publishedOn'] === '2026-03-01' && count($checklist['gaps']) === 2)
        ->has('documents', 1)
        ->where('documents.0', fn ($document) => $document['version'] === 1 && $document['coveredItems'] !== [] && $document['acknowledgements'] === ['acknowledged' => 1, 'expected' => 2])
        ->where('equipment.0', fn ($item) => $item['person'] === 'Ana Ruiz' && $item['received'] === 1 && $item['refused'] === 1 && $item['pending'] === 1 && $item['lastDeliveredOn'] === '2026-02-15')
        ->has('trainings', 1)
        ->where('trainings.0', fn ($training) => $training['heldOn'] === '2026-02-20' && $training['attendees'] === 1 && count($training['topics']) === 2)
        ->where('overdue', fn ($rows) => collect($rows)->pluck('person')->sort()->values()->all() === ['Beto Soto'])
        ->where('notices.reviewed', 1)->where('notices.pending', 1)
        ->where('committee', fn ($committee) => $committee['constitutedOn'] === '2026-01-10' && $committee['members'] === [$this->cora->name] && str_contains($committee['actHref'], '/committee/act')));
});

it('gives the full 5.1 data of the roster to owner and hr, and to the committee everything but marital status, phone and address', function (): void {
    dossierPerson();

    foreach (['owner', 'hr'] as $who) {
        $this->actingAs($this->{$who})->get(dossierUrl())->assertInertia(fn (Assert $page) => $page
            ->where('roster.0', fn ($person) => $person['name'] === 'Ana Ruiz' && $person['maritalStatus'] !== null && $person['contactPhone'] === '4421234567' && str_contains($person['homeAddress'], 'Calle Reforma')));
    }

    $response = $this->actingAs($this->cora)->get(dossierUrl())->assertOk();
    $roster = $response->viewData('page')['props']['roster'][0];
    expect($roster['name'])->toBe('Ana Ruiz')
        ->and($roster['jobTitle'])->toBe('Analista')
        ->and(array_keys($roster))->not->toContain('maritalStatus', 'contactPhone', 'homeAddress')
        ->and(json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR))->not->toContain('4421234567')->not->toContain('Calle Reforma');
});

it('never includes answers, notes, photos, notice descriptions, refusal reasons or IPs', function (): void {
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $ana = dossierPerson();
    $place = Workplace::query()->where('teleworker_id', $ana->id)->firstOrFail();
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $place->id, 'special_needs' => 'NECESIDAD-ESPECIAL']);
    $question = ChecklistQuestion::query()->where('checklist_id', $application->checklist_id)->first()
        ?? ChecklistQuestion::query()->create(['tenant_id' => $this->tenant->id, 'checklist_id' => $application->checklist_id, 'section' => 1, 'position' => 1, 'code' => 'x', 'heading' => 'h', 'text' => 'TEXTO-PREGUNTA', 'origin' => 'dof', 'favorable' => 'yes']);
    ChecklistAnswer::query()->create(['tenant_id' => $this->tenant->id, 'application_id' => $application->id, 'question_id' => $question->id, 'answer' => 'no', 'risk_note' => 'RIESGO-PRIVADO']);
    ChecklistPhoto::query()->create(['tenant_id' => $this->tenant->id, 'application_id' => $application->id, 'path' => 'photos/FOTO-PRIVADA.jpg', 'uploaded_at' => now()]);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Refused, 'refusal_reason' => 'MOTIVO-PRIVADO', 'responded_ip' => '203.0.113.9', 'responded_at' => now()]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'description' => 'DESCRIPCION-AVISO', 'ip' => '203.0.113.10', 'reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'NOTA-REVISION']);
    $policy = TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy]);
    AcknowledgementFactory::new()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $ana->employee_id, 'ip' => '203.0.113.11', 'note' => 'NOTA-ACUSE']);

    $props = $this->actingAs($this->owner)->get(dossierUrl())->assertOk()->viewData('page')['props'];
    $json = json_encode($props, JSON_THROW_ON_ERROR);

    foreach (['NECESIDAD-ESPECIAL', 'RIESGO-PRIVADO', 'FOTO-PRIVADA', 'MOTIVO-PRIVADO', '203.0.113', 'DESCRIPCION-AVISO', 'NOTA-REVISION', 'NOTA-PRIVADA', 'PLAN-PRIVADO', 'NOTA-ACUSE', 'TEXTO-PREGUNTA'] as $secret) {
        expect($json)->not->toContain($secret);
    }

    expect(dossierKeys($props['dossier'] ?? $props))->not->toContain('answers', 'answer', 'risk_note', 'riskNote', 'special_needs', 'specialNeeds', 'photos_list', 'description', 'refusalReason', 'notes', 'actionPlan', 'ip')
        ->and($props['evidence'])->toMatchArray(['checklists' => 1, 'photos' => 1]);
});

it('records every view in the audit trail and the log with ids only', function (): void {
    Log::spy();
    dossierPerson();

    $this->actingAs($this->hr)->get(dossierUrl())->assertOk();
    $this->actingAs($this->cora)->get(dossierUrl())->assertOk();

    $entries = AuditEntry::query()->where('event', 'telework.dossier_viewed')->orderBy('id')->get();
    expect($entries)->toHaveCount(2)
        ->and($entries[0]->metadata)->toBe(['work_center_id' => $this->center->id])
        ->and($entries[0]->actor_id)->toBe($this->hr->id)
        ->and($entries[1]->actor_id)->toBe($this->cora->id);
    Log::shouldHaveReceived('info')->with('telework.dossier_viewed', ['work_center_id' => $this->center->id])->twice();
});

it('is open to owner, hr and the assigned committee only', function (): void {
    foreach (['owner', 'hr', 'cora'] as $who) {
        $this->actingAs($this->{$who})->get(dossierUrl())->assertOk();
    }

    $this->actingAs($this->viewer)->get(dossierUrl())->assertNotFound();
    $this->actingAs($this->cora)->get(dossierUrl($this->other))->assertNotFound();
    expect(AuditEntry::query()->where('event', 'telework.dossier_viewed')->count())->toBe(3);
});

it('is a 404 for another company, an archived or unknown center and a guest', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->create(['archived_at' => now()]);

    $this->actingAs($this->owner);
    $this->get(dossierUrl($foreign))->assertNotFound();
    $this->get(dossierUrl($archived))->assertNotFound();
    $this->get(route('tenant.telework.dossier', [$this->tenant, 'nope']))->assertNotFound();
    $this->get(route('tenant.telework.dossier', [$this->tenant, (string) Illuminate\Support\Str::uuid()]))->assertNotFound();

    auth()->logout();
    $this->get(dossierUrl())->assertRedirect();
});

it('asserts the query count of the dossier and does not grow with people or documents', function (bool $more): void {
    SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => dossierPerson()->id]);

    foreach ([DocumentKind::Policy, DocumentKind::RiskInformation] as $kind) {
        TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => $kind]);
    }

    if ($more) {
        foreach (range(1, 6) as $n) {
            $person = dossierPerson("Persona {$n}");
            EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
            WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
        }

        TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
        TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Reversibility]);
    }

    $this->actingAs($this->owner);
    $this->expectsDatabaseQueryCount(QUERIES_DOSSIER);

    $this->get(dossierUrl())->assertOk();
})->with([false, true]);

it('cuts the trainings at exactly twelve months back, counting the boundary day', function (): void {
    foreach (['2025-02-28', '2025-03-01', '2026-03-01'] as $day) {
        TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => $day]);
    }

    $this->actingAs($this->hr)->get(dossierUrl())->assertInertia(fn (Assert $page) => $page
        ->where('trainings', fn ($trainings) => collect($trainings)->pluck('heldOn')->all() === ['2026-03-01', '2025-03-01']));
});

it('lets an assigned committee member follow the document link of the dossier', function (): void {
    $document = TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy]);
    Storage::disk(config()->string('filesystems.telework_files'))->put($document->path, "%PDF-1.4\n%%EOF\n");

    $href = $this->actingAs($this->cora)->get(dossierUrl())->viewData('page')['props']['documents'][0]['href'];

    $this->get($href)->assertOk();
});

it('marks a person without equipment who justified it', function (): void {
    dossierPerson()->update(['no_equipment_reason' => 'Usa su propio equipo.']);

    $this->actingAs($this->hr)->get(dossierUrl())->assertInertia(fn (Assert $page) => $page
        ->where('equipment.0', fn ($item) => $item['justified'] === true && $item['lastDeliveredOn'] === null && $item['received'] === 0));
});

it('sends the evidence of each row in full', function (): void {
    // Owner and hr can review too, and come first by name: the members list must still be a list.
    $this->hr->update(['name' => 'Alma Recursos']);
    $this->cora->update(['name' => 'Zoe Comisión']);
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'version' => 2]);
    $position = 0;
    foreach ([1 => [Requirement::Lighting], 2 => [Requirement::Chair, Requirement::Noise]] as $section => $lacking) {
        foreach (Requirement::cases() as $requirement) {
            if (! in_array($requirement, $lacking, true)) {
                addQuestion($checklist, ++$position, 'yes', section: $section)->update(['requirement' => $requirement]);
            }
        }
    }
    SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'constituted_on' => '2026-01-10']);
    $ana = dossierPerson();
    $beto = dossierPerson('Beto Soto');
    $beto->update(['started_at' => '2025-01-10 12:00:00']);
    App\Modules\Telework\Models\WorkplaceReview::query()->where('workplace_id', Workplace::query()->where('teleworker_id', $beto->id)->firstOrFail()->id)->update(['decision' => 'not_valid', 'notes' => 'No es seguro.']);
    $policy = TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy, 'covered_items' => ['a', 'b']]);
    $reversibility = TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Reversibility]);
    AcknowledgementFactory::new()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $ana->employee_id]);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Pending, 'delivered_on' => '2026-02-01']);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Received, 'responded_at' => now(), 'delivered_on' => '2026-02-20']);
    $recent = TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => '2026-02-20', 'topics' => ['hazards', 'ict']]);
    TrainingAttendee::query()->create(['tenant_id' => $this->tenant->id, 'training_id' => $recent->id, 'employee_id' => $ana->employee_id]);
    // Two work risks pending and one reviewed: the kind adds both groups, and reviewed and pending do not swap.
    WorkerNoticeFactory::new()->count(2)->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'kind' => NoticeKind::WorkRisk]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $beto->id, 'kind' => NoticeKind::WorkRisk, 'reviewed_by' => $this->hr->id, 'reviewed_at' => now(), 'review_note' => 'Revisado.']);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $beto->id, 'kind' => NoticeKind::ConditionChange]);
    $labels = fn (array $requirements): array => array_values(array_map(fn (Requirement $requirement): string => $requirement->label(), array_filter(Requirement::cases(), fn (Requirement $requirement): bool => in_array($requirement, $requirements, true))));
    $items = fn (array $letters): array => array_map(fn (string $letter): string => "{$letter}) ".DocumentKind::Policy->items()[$letter], $letters);
    $address = 'Calle Reforma 12, Centro, 76000 Querétaro, Querétaro';
    $review = ['decidedOn' => '2026-03-01', 'method' => App\Modules\Telework\Enums\ReviewMethod::RemoteCheck->label()];

    $this->actingAs($this->hr)->get(dossierUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('workCenterId', $this->center->id)
        ->where('header', fn ($header): bool => array_keys($header->all()) === ['legalName', 'rfc', 'workCenter', 'address', 'generatedOn', 'generatedBy']
            && $header['legalName'] === 'Acme Manufactura SA de CV' && $header['rfc'] === $this->tenant->rfc && $header['workCenter'] === 'Planta Norte'
            && str_contains($header['address'], $this->center->street) && $header['generatedOn'] === '2026-03-01' && $header['generatedBy'] === 'Alma Recursos')
        ->where('rows.0', ['numeral' => '5.1', 'label' => 'Listado de personas teletrabajadoras', 'state' => 'done', 'stateLabel' => 'Cumple', 'detail' => '2 personas en el padrón.'])
        ->where('roster', [
            ['name' => 'Ana Ruiz', 'jobTitle' => 'Analista', 'percentage' => 50, 'startedOn' => '2026-03-01', 'maritalStatus' => 'single', 'contactPhone' => '4421234567', 'homeAddress' => $address],
            ['name' => 'Beto Soto', 'jobTitle' => 'Analista', 'percentage' => 50, 'startedOn' => '2025-01-10', 'maritalStatus' => 'single', 'contactPhone' => '4421234567', 'homeAddress' => $address],
        ])
        ->where('workplaces', [
            ['person' => 'Ana Ruiz', 'status' => 'valid', 'statusLabel' => 'Validado', 'review' => ['decidedOn' => $review['decidedOn'], 'decision' => 'Validado', 'method' => $review['method'], 'validUntil' => '2027-02-28']],
            ['person' => 'Beto Soto', 'status' => 'not_valid', 'statusLabel' => 'No apto', 'review' => ['decidedOn' => $review['decidedOn'], 'decision' => 'No validado', 'method' => $review['method'], 'validUntil' => null]],
        ])
        ->where('checklist', ['version' => 2, 'publishedOn' => '2026-03-01', 'gaps' => [
            ['section' => 1, 'requirements' => $labels([Requirement::Lighting])],
            ['section' => 2, 'requirements' => $labels([Requirement::Chair, Requirement::Noise])],
        ]])
        ->where('documents', [
            ['numeral' => '5.2', 'kind' => 'Política de Teletrabajo', 'version' => 1, 'publishedOn' => '2026-03-01', 'coveredItems' => $items(['a', 'b']), 'missingItems' => $items(['c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l']), 'acknowledgements' => ['acknowledged' => 1, 'expected' => 2], 'href' => route('tenant.telework.documents.file', [$this->tenant, $policy->id])],
            ['numeral' => '5.10', 'kind' => 'Mecanismo de reversibilidad', 'version' => 1, 'publishedOn' => '2026-03-01', 'coveredItems' => [], 'missingItems' => [], 'acknowledgements' => null, 'href' => route('tenant.telework.documents.file', [$this->tenant, $reversibility->id])],
        ])
        ->where('equipment', [
            ['person' => 'Ana Ruiz', 'received' => 1, 'refused' => 0, 'pending' => 1, 'lastDeliveredOn' => '2026-02-20', 'justified' => true],
            ['person' => 'Beto Soto', 'received' => 0, 'refused' => 0, 'pending' => 0, 'lastDeliveredOn' => null, 'justified' => true],
        ])
        ->where('trainings', [['heldOn' => '2026-02-20', 'modality' => $recent->modality->label(), 'topics' => [App\Modules\Telework\Enums\TrainingTopic::Hazards->label(), App\Modules\Telework\Enums\TrainingTopic::Ict->label()], 'attendees' => 1]])
        ->where('overdue', [['person' => 'Beto Soto', 'dueOn' => '2026-01-10']])
        ->where('notices', ['byKind' => [
            ['label' => NoticeKind::ConditionChange->label(), 'count' => 1],
            ['label' => NoticeKind::WorkRisk->label(), 'count' => 3],
        ], 'reviewed' => 1, 'pending' => 3])
        ->where('committee', ['constitutedOn' => '2026-01-10', 'members' => ['Zoe Comisión'], 'actHref' => null])
        ->where('evidence', ['checklists' => 0, 'photos' => 0, 'href' => route('tenant.telework.reviews.index', [$this->tenant, $this->center->id])]));
});
