<?php

declare(strict_types=1);

use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\RowState;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\ComplianceRows;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\TrainingFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;

mutates(ComplianceRows::class);

const NUMERALS = ['5.1', '5.1.1', '5.1.2', '5.2', '5.3', '5.4 y 7', '5.5', '5.5.1', '5.6', '5.7', '5.8', '5.9 y 8', '5.10', '5.11', '5.12', '5.13', '5.14'];

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    workIn($this->tenant);
});

/** An active person of the test's center. */
function rowPerson(array $state = []): Teleworker
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id]);

    return TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employee->id, ...$state]);
}

/** A workplace of a person with the review (`valid`, `plan`, `not_valid`, `lapsed`) the test asks for; none for `none`. */
function rowPlace(Teleworker $person, string $review = 'valid'): Workplace
{
    $place = WorkplaceFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => $person->id]);
    $factory = match ($review) {
        'none' => null,
        'not_valid' => WorkplaceReviewFactory::new()->notValid(),
        'plan' => WorkplaceReviewFactory::new()->withPlan(),
        'lapsed' => WorkplaceReviewFactory::new()->decidedAt('2025-01-10 18:00:00'),
        default => WorkplaceReviewFactory::new(),
    };
    $factory?->create(['tenant_id' => test()->tenant->id, 'workplace_id' => $place->id]);

    return $place;
}

/** @param list<string> $items */
function rowDocument(DocumentKind $kind, array $items = []): void
{
    TeleworkDocumentFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'kind' => $kind, 'covered_items' => $items]);
}

/**
 * A published list that covers every requirement of both sections, except those named.
 *
 * @param  list<Requirement>  $lackingOne
 * @param  list<Requirement>  $lackingTwo
 */
function rowChecklist(bool $withSectionTwo = true, array $lackingOne = [], array $lackingTwo = [], int $version = 1): void
{
    $checklist = ChecklistFactory::new()->published()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'version' => $version]);
    $position = 0;

    foreach (array_filter([1 => true, 2 => $withSectionTwo]) as $section => $_) {
        foreach (Requirement::cases() as $requirement) {
            if (! in_array($requirement, $section === 1 ? $lackingOne : $lackingTwo, true)) {
                addQuestion($checklist, ++$position, 'yes', section: $section)->update(['requirement' => $requirement]);
            }
        }
    }

    // A section without the covering questions still has questions of its own.
    if ($withSectionTwo && count($lackingTwo) === count(Requirement::cases())) {
        addQuestion($checklist, ++$position, 'yes', section: 2);
    }
}

/** @return array<string, array{state: RowState, detail: string|null}> */
function computedRows(): array
{
    $rows = [];

    foreach (resolve(ComplianceRows::class)->for(test()->center->id) as $row) {
        $rows[$row->numeral] = ['state' => $row->state, 'detail' => $row->detail, 'label' => $row->label];
    }

    return $rows;
}

function rowState(string $numeral): RowState
{
    return computedRows()[$numeral]['state'];
}

it('lists the 17 rows of table 10.3 in order, each with a label', function (): void {
    $rows = resolve(ComplianceRows::class)->for($this->center->id);

    expect(array_map(fn ($row): string => $row->numeral, $rows))->toBe(NUMERALS)
        ->and(array_filter(array_map(fn ($row): string => $row->label, $rows), fn (string $label): bool => $label === ''))->toBe([]);
});

it('marks every row not applicable or missing in a center with nothing', function (): void {
    $states = array_map(fn (array $row): string => $row['state']->value, computedRows());

    expect($states)->toBe([
        '5.1' => 'not_applicable', '5.1.1' => 'not_applicable', '5.1.2' => 'not_applicable', '5.2' => 'missing', '5.3' => 'missing',
        '5.4 y 7' => 'missing', '5.5' => 'not_applicable', '5.5.1' => 'missing', '5.6' => 'missing', '5.7' => 'not_applicable',
        '5.8' => 'missing', '5.9 y 8' => 'not_applicable', '5.10' => 'missing', '5.11' => 'not_applicable', '5.12' => 'missing',
        '5.13' => 'missing', '5.14' => 'missing',
    ]);
});

it('computes each 10.3 row', function (string $numeral, Closure $arrange, RowState $expected): void {
    $arrange();

    expect(rowState($numeral))->toBe($expected);
})->with([
    '5.1 done' => ['5.1', fn () => rowPerson(), RowState::Done],
    '5.1 missing: only aspirants' => ['5.1', fn () => TeleworkerFactory::new()->create(['tenant_id' => test()->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id])->id]), RowState::Missing],
    '5.1.1 done' => ['5.1.1', fn () => rowPlace(rowPerson()), RowState::Done],
    '5.1.1 missing: a person without places' => ['5.1.1', fn () => rowPerson(), RowState::Missing],
    '5.1.1 partial: one place pending' => ['5.1.1', function (): void {
        rowPlace(rowPerson());
        rowPlace(rowPerson(), 'none');
    }, RowState::Partial],
    '5.1.2 done' => ['5.1.2', fn () => rowPlace(rowPerson()), RowState::Done],
    '5.1.2 missing: the only place is not apt' => ['5.1.2', fn () => rowPlace(rowPerson(), 'not_valid'), RowState::Missing],
    '5.1.2 partial: one person has none in force' => ['5.1.2', function (): void {
        rowPlace(rowPerson(), 'plan');
        rowPlace(rowPerson(), 'lapsed');
    }, RowState::Partial],
    '5.2 done' => ['5.2', fn () => rowDocument(DocumentKind::Policy, DocumentKind::Policy->coverableItems()), RowState::Done],
    '5.2 missing' => ['5.2', fn () => null, RowState::Missing],
    '5.2 partial: not every inciso' => ['5.2', fn () => rowDocument(DocumentKind::Policy, ['a', 'b']), RowState::Partial],
    '5.3 done: nobody to inform' => ['5.3', fn () => rowDocument(DocumentKind::RiskInformation), RowState::Done],
    '5.3 missing' => ['5.3', fn () => null, RowState::Missing],
    '5.3 partial: the person did not confirm' => ['5.3', function (): void {
        rowDocument(DocumentKind::RiskInformation);
        rowPerson();
    }, RowState::Partial],
    '5.4 y 7 done' => ['5.4 y 7', function (): void {
        rowChecklist();
        rowPlace(rowPerson(), 'lapsed');
    }, RowState::Done],
    '5.4 y 7 missing: no list' => ['5.4 y 7', fn () => rowPlace(rowPerson()), RowState::Missing],
    '5.4 y 7 partial: a place without decision' => ['5.4 y 7', function (): void {
        rowChecklist();
        rowPlace(rowPerson());
        rowPlace(rowPerson(), 'none');
    }, RowState::Partial],
    '5.4 y 7 partial: Sección I lacks a requirement' => ['5.4 y 7', function (): void {
        rowChecklist(lackingOne: [Requirement::Lighting]);
        rowPlace(rowPerson());
    }, RowState::Partial],
    '5.5 done' => ['5.5', fn () => rowPlace(rowPerson(), 'plan'), RowState::Done],
    '5.5 missing: no place in force' => ['5.5', fn () => rowPlace(rowPerson(), 'none'), RowState::Missing],
    '5.5 partial: one place lapsed' => ['5.5', function (): void {
        rowPlace(rowPerson());
        rowPlace(rowPerson(), 'lapsed');
    }, RowState::Partial],
    '5.5.1 done' => ['5.5.1', function (): void {
        rowChecklist();
        rowPlace(rowPerson());
    }, RowState::Done],
    '5.5.1 missing: no published list' => ['5.5.1', fn () => rowPlace(rowPerson()), RowState::Missing],
    '5.5.1 partial: no Sección II' => ['5.5.1', fn () => rowChecklist(withSectionTwo: false), RowState::Partial],
    '5.5.1 partial: a periodic review is overdue' => ['5.5.1', function (): void {
        rowChecklist();
        rowPlace(rowPerson(), 'lapsed');
    }, RowState::Partial],
    '5.5.1 partial: Sección II lacks a requirement' => ['5.5.1', function (): void {
        rowChecklist(lackingTwo: [Requirement::Chair]);
        rowPlace(rowPerson());
    }, RowState::Partial],
    '5.6 done' => ['5.6', fn () => rowDocument(DocumentKind::ImplementationProcess, ['a', 'b', 'c', 'd']), RowState::Done],
    '5.6 missing' => ['5.6', fn () => null, RowState::Missing],
    '5.6 partial: only a to c' => ['5.6', fn () => rowDocument(DocumentKind::ImplementationProcess, ['a', 'b', 'c']), RowState::Partial],
    // C-22: the person's signed receipt (or signed refusal) is the evidence; a registration they have not answered is not.
    '5.7 done: equipment received' => ['5.7', fn () => EquipmentItemFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson(['no_equipment_reason' => null])->id, 'status' => EquipmentStatus::Received, 'responded_at' => now()]), RowState::Done],
    '5.7 partial: equipment only pending' => ['5.7', fn () => EquipmentItemFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson(['no_equipment_reason' => null])->id]), RowState::Partial],
    '5.7 done: justified' => ['5.7', fn () => rowPerson(), RowState::Done],
    '5.7 missing: no equipment and no reason' => ['5.7', fn () => rowPerson(['no_equipment_reason' => null]), RowState::Missing],
    '5.7 partial: one of two' => ['5.7', function (): void {
        rowPerson();
        rowPerson(['no_equipment_reason' => null]);
        EquipmentItemFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson(['no_equipment_reason' => null])->id, 'status' => EquipmentStatus::Refused, 'refusal_reason' => 'Prefiere el suyo.', 'responded_at' => now()]);
    }, RowState::Partial],
    '5.8 done' => ['5.8', fn () => rowDocument(DocumentKind::MaintenanceProgram), RowState::Done],
    '5.8 missing' => ['5.8', fn () => null, RowState::Missing],
    '5.9 y 8 done' => ['5.9 y 8', function (): void {
        $person = rowPerson(['started_at' => '2025-12-01 12:00:00']);
        $training = TrainingFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'held_on' => '2026-02-20']);
        TrainingAttendee::query()->create(['tenant_id' => test()->tenant->id, 'training_id' => $training->id, 'employee_id' => $person->employee_id]);
    }, RowState::Done],
    '5.9 y 8 done: a new person in their first twelve months' => ['5.9 y 8', fn () => rowPerson(), RowState::Done],
    '5.9 y 8 missing: everyone is overdue' => ['5.9 y 8', function (): void {
        rowPerson(['started_at' => '2025-01-10 12:00:00']);
        TrainingFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'held_on' => '2025-01-05']);
    }, RowState::Missing],
    '5.9 y 8 missing: nobody ever trained and overdue' => ['5.9 y 8', fn () => rowPerson(['started_at' => '2025-01-10 12:00:00']), RowState::Missing],
    '5.9 y 8 partial: only some are overdue' => ['5.9 y 8', function (): void {
        rowPerson(['started_at' => '2025-01-10 12:00:00']);
        $trained = rowPerson(['started_at' => '2025-12-01 12:00:00']);
        $training = TrainingFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'held_on' => '2026-02-20']);
        TrainingAttendee::query()->create(['tenant_id' => test()->tenant->id, 'training_id' => $training->id, 'employee_id' => $trained->employee_id]);
    }, RowState::Partial],
    '5.10 done' => ['5.10', fn () => rowDocument(DocumentKind::Reversibility), RowState::Done],
    '5.10 missing' => ['5.10', fn () => null, RowState::Missing],
    '5.11 done: all reviewed' => ['5.11', fn () => WorkerNoticeFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson()->id, 'reviewed_at' => now(), 'review_note' => 'Atendido.', 'reviewed_by' => memberOf(test()->tenant, TenantRole::Hr)->id]), RowState::Done],
    '5.11 missing: none reviewed' => ['5.11', fn () => WorkerNoticeFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson()->id]), RowState::Missing],
    '5.11 partial: one of two' => ['5.11', function (): void {
        $person = rowPerson();
        WorkerNoticeFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => $person->id, 'reviewed_at' => now(), 'review_note' => 'Atendido.', 'reviewed_by' => memberOf(test()->tenant, TenantRole::Hr)->id]);
        WorkerNoticeFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => $person->id]);
    }, RowState::Partial],
    '5.11 ignores the notices that are not a risk of work' => ['5.11', fn () => WorkerNoticeFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson()->id, 'kind' => NoticeKind::ConditionChange]), RowState::NotApplicable],
    '5.12 done' => ['5.12', function (): void {
        SafetyCommitteeFactoryForRows();
        CommitteeAssignment::factory()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'user_id' => memberOf(test()->tenant, TenantRole::Committee)->id]);
    }, RowState::Done],
    '5.12 missing' => ['5.12', fn () => null, RowState::Missing],
    '5.12 partial: committee without members' => ['5.12', fn () => SafetyCommitteeFactoryForRows(), RowState::Partial],
    '5.12 partial: the only member lost the role' => ['5.12', function (): void {
        SafetyCommitteeFactoryForRows();
        CommitteeAssignment::factory()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'user_id' => memberOf(test()->tenant, TenantRole::Viewer)->id]);
    }, RowState::Partial],
    '5.13 done' => ['5.13', fn () => rowDocument(DocumentKind::FamilyViolence), RowState::Done],
    '5.13 missing' => ['5.13', fn () => null, RowState::Missing],
    '5.14 done' => ['5.14', fn () => rowDocument(DocumentKind::CommitteeParticipation), RowState::Done],
    '5.14 missing' => ['5.14', fn () => null, RowState::Missing],
]);

function SafetyCommitteeFactoryForRows(): SafetyCommittee
{
    return SafetyCommittee::factory()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id]);
}

it('counts a person once in the roster whatever the number of places, and ignores another center', function (): void {
    rowPlace(rowPerson());
    rowPlace(rowPerson(), 'none');
    $elsewhere = WorkCenter::factory()->for($this->tenant)->create();
    $stranger = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $elsewhere->id]);
    rowPlace(TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $stranger->id]), 'not_valid');

    expect(computedRows()['5.1']['detail'])->toBe('2 personas en el padrón.')
        ->and(computedRows()['5.1.1']['detail'])->toBe('1 de 2 lugares validados y vigentes.');
});

it('does not count a retired workplace or a person who stopped teleworking', function (): void {
    $moved = rowPlace(rowPerson(), 'none');
    $moved->update(['retired_at' => now()]);
    rowPlace(rowPerson());
    rowPlace(TeleworkerFactory::new()->ended()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]), 'not_valid');

    expect(computedRows()['5.1.1'])->toMatchArray(['state' => RowState::Done, 'detail' => '1 de 1 lugar validado y vigente.']);
});

it('says in 5.3 how many confirmed the risk information', function (): void {
    rowDocument(DocumentKind::RiskInformation);
    $ana = rowPerson();
    rowPerson();
    $document = Illuminate\Support\Facades\DB::table('telework_documents')->first();
    Acknowledgement::factory()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $document->id, 'employee_id' => $ana->employee_id]);

    expect(computedRows()['5.3'])->toMatchArray(['state' => RowState::Partial, 'detail' => '1 de 2 confirmaron que lo leyeron.']);
});

it('names the incisos a policy still lacks', function (): void {
    rowDocument(DocumentKind::Policy, ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);

    expect(computedRows()['5.2']['detail'])->toContain('Faltan los incisos k, l');
});

it('uses only the version in force of a document', function (): void {
    rowDocument(DocumentKind::Reversibility);
    TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy, 'version' => 1, 'covered_items' => ['a']]);
    TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy, 'version' => 2, 'covered_items' => DocumentKind::Policy->coverableItems()]);

    expect(rowState('5.2'))->toBe(RowState::Done);
});

it('says in 5.11 that medical exams are outside what Humetría covers', function (): void {
    expect(computedRows()['5.11']['detail'])->toContain('exámenes médicos');
});

it('does not read another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = WorkCenter::factory()->for($other)->create();
    TeleworkDocumentFactory::new()->create(['tenant_id' => $other->id, 'work_center_id' => $foreign->id, 'kind' => DocumentKind::MaintenanceProgram]);
    workIn($other);
    $mine = resolve(ComplianceRows::class)->for($this->center->id);
    workIn($this->tenant);

    expect(collect($mine)->firstWhere('numeral', '5.8')?->state)->toBe(RowState::Missing);
});

it('tells what to do about a workplace the Comisión judged not apt', function (): void {
    rowPlace(rowPerson(), 'not_valid');

    expect(computedRows()['5.1.1']['detail'])->toContain('1 lugar no es apto: retíralo o inicia la reversibilidad')
        ->and(computedRows()['5.5']['detail'])->toContain('retíralo o inicia la reversibilidad');
});

it('names the requirements a published list leaves uncovered', function (): void {
    rowChecklist(lackingOne: [Requirement::Lighting], lackingTwo: [Requirement::Chair, Requirement::Noise]);
    rowPlace(rowPerson());

    expect(computedRows()['5.4 y 7']['detail'])->toContain(Requirement::Lighting->label())
        ->and(computedRows()['5.5.1']['detail'])->toContain(Requirement::Chair->label())->toContain(Requirement::Noise->label());
});

it('says in 5.7 how many people have equipment nobody confirmed', function (): void {
    EquipmentItemFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => rowPerson(['no_equipment_reason' => null])->id]);

    expect(computedRows()['5.7']['detail'])->toContain('equipo sin acuse');
});

it('computes the rows of many centers at once, each as on its own', function (): void {
    rowPlace(rowPerson());
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $many = resolve(ComplianceRows::class)->forMany([$this->center->id, $other->id]);

    expect(array_keys($many))->toBe([$this->center->id, $other->id])
        ->and($many[$this->center->id])->toEqual(resolve(ComplianceRows::class)->for($this->center->id))
        ->and($many[$other->id])->toEqual(resolve(ComplianceRows::class)->for($other->id))
        ->and($many[$other->id][0]->state)->toBe(RowState::NotApplicable);
});

/** @return array<string, string> numeral => «state: detail» of every row of the test's center. */
function rowWords(): array
{
    return array_map(fn (array $row): string => "{$row['state']->value}: {$row['detail']}", computedRows());
}

it('describes each row in words, with its counts, in a center half way through', function (): void {
    // Ana: trained this period, equipment received, two places in force.
    $ana = rowPerson(['started_at' => '2025-12-01 12:00:00', 'no_equipment_reason' => null]);
    rowPlace($ana);
    rowPlace($ana);
    $training = TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => '2026-02-20']);
    TrainingAttendee::query()->create(['tenant_id' => $this->tenant->id, 'training_id' => $training->id, 'employee_id' => $ana->employee_id]);
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'status' => EquipmentStatus::Received, 'responded_at' => now()]);
    // Beto: never trained in more than twelve months, justified without equipment, his only place not apt.
    rowPlace(rowPerson(['started_at' => '2025-01-10 12:00:00']), 'not_valid');
    // Ciro: due for training next month, equipment not answered, a lapsed place and one without decision.
    $ciro = rowPerson(['started_at' => '2025-03-20 12:00:00', 'no_equipment_reason' => null]);
    rowPlace($ciro, 'lapsed');
    rowPlace($ciro, 'none');
    EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ciro->id]);
    // Two aspirants do not count while there are people in the roster.
    foreach (range(1, 2) as $n) {
        TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
    }
    rowDocument(DocumentKind::Policy, ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j']);
    rowDocument(DocumentKind::RiskInformation);
    rowDocument(DocumentKind::ImplementationProcess, ['a', 'b']);
    rowDocument(DocumentKind::FamilyViolence);
    foreach (Illuminate\Support\Facades\DB::table('telework_documents')->whereIn('kind', [DocumentKind::Policy->value, DocumentKind::RiskInformation->value])->pluck('id') as $documentId) {
        Acknowledgement::factory()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $documentId, 'employee_id' => $ana->employee_id]);
    }
    rowChecklist(lackingOne: [Requirement::Lighting], lackingTwo: [Requirement::Chair, Requirement::Noise]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ana->id, 'reviewed_at' => now(), 'review_note' => 'Atendido.', 'reviewed_by' => memberOf($this->tenant, TenantRole::Hr)->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $ciro->id]);
    SafetyCommitteeFactoryForRows();
    foreach (range(1, 2) as $n) {
        CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => memberOf($this->tenant, TenantRole::Committee)->id]);
    }

    expect(rowWords())->toBe([
        '5.1' => 'done: 3 personas en el padrón.',
        '5.1.1' => 'partial: 2 de 5 lugares validados y vigentes. 1 lugar no es apto: retíralo o inicia la reversibilidad.',
        '5.1.2' => 'partial: 1 de 3 personas con un lugar validado y vigente.',
        // The policy is for all the staff of the center: the three people and the two aspirants.
        '5.2' => 'partial: Versión 1. Faltan los incisos k, l. 1 de 5 personas confirmaron que la leyeron.',
        // The risk information is for the roster and the Comisión: three people and two members.
        '5.3' => 'partial: 1 de 5 confirmaron que lo leyeron.',
        '5.4 y 7' => 'partial: 4 de 5 lugares con decisión de la Comisión. A la Sección I le falta cubrir: '.Requirement::Lighting->label().'.',
        '5.5' => 'partial: 2 de 5 lugares con validación vigente de la Comisión. 1 lugar no es apto: retíralo o inicia la reversibilidad.',
        '5.5.1' => 'partial: A la Sección II le falta cubrir: '.Requirement::Noise->label().', '.Requirement::Chair->label().'.',
        '5.6' => 'partial: Versión 1. Faltan los incisos c, d.',
        '5.7' => 'partial: 2 de 3 personas con equipo confirmado o con la justificación de no recibirlo. 1 persona con equipo sin acuse de recibido.',
        '5.8' => 'missing: Aún no subes este documento.',
        '5.9 y 8' => 'partial: 1 persona tiene su capacitación vencida (más de 12 meses).',
        '5.10' => 'missing: Aún no subes este documento.',
        '5.11' => 'partial: 1 de 2 avisos de riesgo de trabajo revisados. Los exámenes médicos del 5.11 no los cubre Humetría: llévalos por separado.',
        '5.12' => 'done: 2 integrantes con acceso al expediente.',
        '5.13' => 'done: Versión 1.',
        '5.14' => 'missing: Aún no subes este documento.',
    ]);
});

it('describes each row in words in a center with one person up to date', function (): void {
    $ana = rowPerson(['started_at' => '2025-03-20 12:00:00']);
    rowPlace($ana);
    rowDocument(DocumentKind::Policy, DocumentKind::Policy->coverableItems());
    rowDocument(DocumentKind::RiskInformation);
    foreach (Illuminate\Support\Facades\DB::table('telework_documents')->pluck('id') as $documentId) {
        Acknowledgement::factory()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => $documentId, 'employee_id' => $ana->employee_id]);
    }
    rowChecklist();
    SafetyCommitteeFactoryForRows();
    $member = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $member->id]);
    Acknowledgement::factory()->acknowledged()->create(['tenant_id' => $this->tenant->id, 'document_id' => Illuminate\Support\Facades\DB::table('telework_documents')->where('kind', DocumentKind::RiskInformation->value)->value('id'), 'employee_id' => null, 'user_id' => $member->id]);

    expect(rowWords())->toBe([
        '5.1' => 'done: 1 persona en el padrón.',
        '5.1.1' => 'done: 1 de 1 lugar validado y vigente.',
        '5.1.2' => 'done: 1 de 1 persona con un lugar validado y vigente.',
        '5.2' => 'done: Versión 1. 1 de 1 persona confirmó que la leyó.',
        '5.3' => 'done: 2 de 2 confirmaron que lo leyeron.',
        '5.4 y 7' => 'done: 1 de 1 lugar con decisión de la Comisión.',
        '5.5' => 'done: 1 de 1 lugar con validación vigente de la Comisión.',
        '5.5.1' => 'done: Sección II publicada y ninguna revisión periódica vencida.',
        '5.6' => 'missing: Aún no subes este documento.',
        '5.7' => 'done: 1 de 1 persona con equipo confirmado o con la justificación de no recibirlo.',
        '5.8' => 'missing: Aún no subes este documento.',
        // Twelve months after 2025-03-20 is less than a month away.
        '5.9 y 8' => 'done: 1 persona debe capacitarse en el próximo mes.',
        '5.10' => 'missing: Aún no subes este documento.',
        '5.11' => 'not_applicable: Nadie ha avisado de un riesgo de trabajo. Los exámenes médicos del 5.11 no los cubre Humetría: llévalos por separado.',
        '5.12' => 'done: 1 integrante con acceso al expediente.',
        '5.13' => 'missing: Aún no subes este documento.',
        '5.14' => 'missing: Aún no subes este documento.',
    ]);
});

it('describes each row in words in a center with documents and a list but nobody yet', function (): void {
    rowDocument(DocumentKind::Policy, DocumentKind::Policy->coverableItems());
    rowChecklist();
    foreach (range(1, 2) as $n) {
        TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
    }

    expect(rowWords())->toBe([
        '5.1' => 'missing: Hay 2 aspirantes, pero ninguna persona en el padrón.',
        '5.1.1' => 'not_applicable: Nadie teletrabaja en este centro todavía.',
        '5.1.2' => 'not_applicable: Nadie teletrabaja en este centro todavía.',
        '5.2' => 'done: Versión 1. 0 de 2 personas confirmaron que la leyeron.',
        '5.3' => 'missing: Aún no subes la información sobre los riesgos del Teletrabajo.',
        '5.4 y 7' => 'done: Lista publicada (versión 1); no hay lugares por validar.',
        '5.5' => 'not_applicable: Nadie teletrabaja en este centro todavía.',
        '5.5.1' => 'done: Sección II publicada y ninguna revisión periódica vencida.',
        '5.6' => 'missing: Aún no subes este documento.',
        '5.7' => 'not_applicable: Nadie teletrabaja en este centro todavía.',
        '5.8' => 'missing: Aún no subes este documento.',
        '5.9 y 8' => 'not_applicable: Nadie teletrabaja en este centro todavía.',
        '5.10' => 'missing: Aún no subes este documento.',
        '5.11' => 'not_applicable: Nadie ha avisado de un riesgo de trabajo. Los exámenes médicos del 5.11 no los cubre Humetría: llévalos por separado.',
        '5.12' => 'missing: Registra la Comisión de seguridad e higiene del centro.',
        '5.13' => 'missing: Aún no subes este documento.',
        '5.14' => 'missing: Aún no subes este documento.',
    ]);
});

it('words the policy in the singular while nobody works in the center yet', function (): void {
    rowDocument(DocumentKind::Policy, DocumentKind::Policy->coverableItems());

    expect(rowWords()['5.2'])->toBe('done: Versión 1. 0 de 1 persona confirmó que la leyó.');
});

it('words the rows that have nothing to count yet', function (): void {
    rowDocument(DocumentKind::RiskInformation);
    rowChecklist(withSectionTwo: false);

    expect(rowWords())->toMatchArray([
        '5.3' => 'done: Aún no hay personas ni integrantes de la Comisión a quienes informar.',
        '5.5.1' => 'partial: La lista publicada no tiene Sección II, la que contestan las personas cada periodo.',
    ]);
});

it('says that everyone is up to date with their training', function (): void {
    $ana = rowPerson(['started_at' => '2025-12-01 12:00:00']);
    $training = TrainingFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'held_on' => '2026-02-20']);
    TrainingAttendee::query()->create(['tenant_id' => $this->tenant->id, 'training_id' => $training->id, 'employee_id' => $ana->employee_id]);

    expect(rowWords()['5.9 y 8'])->toBe('done: Todas las personas están al corriente.');
});

it('marks 5.4 y 7 missing while the only place has no decision', function (): void {
    rowChecklist();
    rowPlace(rowPerson(), 'none');

    expect(rowWords()['5.4 y 7'])->toBe('missing: 0 de 1 lugar con decisión de la Comisión.');
});

it('says in 5.5 that nobody has a workplace yet', function (): void {
    rowPerson();

    expect(rowWords()['5.5'])->toBe('missing: Ninguna persona tiene registrado un lugar de trabajo.');
});

it('does not take an empty reason as the justification of having no equipment', function (): void {
    rowPerson(['no_equipment_reason' => '']);

    expect(rowWords()['5.7'])->toBe('missing: 0 de 1 persona con equipo confirmado o con la justificación de no recibirlo.');
});

it('reads the newest published list of the center', function (): void {
    rowChecklist(lackingOne: [Requirement::Lighting]);
    rowChecklist(version: 2);

    expect(rowWords()['5.4 y 7'])->toBe('done: Lista publicada (versión 2); no hay lugares por validar.');
});

it('counts in 5.5.1 only the places whose validation lapsed', function (): void {
    rowChecklist();
    rowPlace(rowPerson());
    rowPlace(rowPerson(), 'lapsed');
    rowPlace(rowPerson(), 'not_valid');

    expect(rowWords()['5.5.1'])->toBe('partial: 1 lugar tiene vencida su revisión periódica.');
});

it('reads nothing for no centers, and a center with nothing in a fixed number of queries', function (): void {
    Illuminate\Support\Facades\DB::enableQueryLog();

    expect(resolve(ComplianceRows::class)->forMany([]))->toBe([])
        ->and(Illuminate\Support\Facades\DB::getQueryLog())->toBe([]);

    resolve(ComplianceRows::class)->for($this->center->id);

    expect(Illuminate\Support\Facades\DB::getQueryLog())->toHaveCount(10);
});
