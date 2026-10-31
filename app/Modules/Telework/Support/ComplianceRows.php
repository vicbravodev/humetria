<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\RowState;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\TrainingStanding;
use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistQuestion;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use Carbon\CarbonImmutable;

/**
 * Where each row of the inspection table 10.3 (DOF 08/06/2023) stands in a work center (criterio C-30), in the table's
 * order. A row is `done` when the evidence it asks for is there, `partial` when only part of it is, `missing` when none
 * is and `not_applicable` when the center has nobody it could apply to. Read in a fixed number of queries whatever the
 * number of people, workplaces or documents.
 *
 * - 5.1 Listado: done with at least one active person (the 5.1 data is guaranteed by the database); missing when there
 *   are only aspirants; not applicable with neither.
 * - 5.1.1 Conectividad y condiciones: of the workplaces of the active people, those whose latest Comisión decision
 *   validates and has not lapsed (`WorkplaceStanding`). Missing with none, partial with some. Not applicable without
 *   active people; missing when they have no workplace.
 * - 5.1.2 Lugares fijos y convenidos: the active people with at least one validated workplace in force.
 * - 5.2 Política: the version in force; partial until it covers the incisos a) to l). The people who confirmed it are
 *   told in the detail, not in the state.
 * - 5.3 Riesgos: the information in force; partial until everyone expected (the roster and the Comisión) confirmed it.
 * - 5.4 y 7 Lista de verificación: a published list, and every workplace with a decision of the Comisión (any decision,
 *   even one that lapsed: the validation was made).
 * - 5.5 Validación: as 5.1.1, from the Comisión's side; the detail says how many workplaces are not apt, where nobody
 *   teleworks.
 * - 5.5.1 Segunda versión periódica: a published list with Sección II and no validation lapsed (periodic review overdue).
 * - 5.6 Proceso de implementación: the document; partial until it covers the incisos a) to d).
 * - 5.7 Equipo: the active people with equipment registered (received, pending or refused with its reason) or with the
 *   justification of having none.
 * - 5.8, 5.10, 5.13, 5.14: the document of that kind in force.
 * - 5.9 y 8 Capacitación: missing until a training is registered in the center; partial while someone is past twelve
 *   months since their last (`TrainingDue`); done otherwise. Not applicable without active people.
 * - 5.11 Avisos: the notices of a risk of work (the accident notices the numeral asks to follow up), reviewed against all.
 *   Not applicable if there never was one. The medical exams of the numeral are outside what Humetría covers (criterios),
 *   and the detail says so.
 * - 5.12 Comisión: registered and with at least one member who still holds a reviewing role (so, with access).
 */
final readonly class ComplianceRows
{
    public function __construct(
        private WorkplaceStanding $standing,
        private Settings $settings,
        private AckProgress $acknowledgements,
        private TrainingDue $training,
        private Members $members,
    ) {}

    /** @return list<ComplianceRow> */
    public function for(string $workCenterId): array
    {
        $people = Teleworker::query()->where('work_center_id', $workCenterId)->whereIn('status', [TeleworkerStatus::Active, TeleworkerStatus::Aspirant])
            ->get(['id', 'employee_id', 'status', 'started_at', 'created_at', 'no_equipment_reason']);
        $active = array_values($people->where('status', TeleworkerStatus::Active)->all());
        $aspirants = $people->count() - count($active);
        $places = $this->places($workCenterId, count($active));
        $documents = $this->documents($workCenterId);
        $checklist = Checklist::latestPublished($workCenterId);
        $acks = $this->acknowledgements->forDocuments(array_values(array_filter([$documents[DocumentKind::Policy->value] ?? null, $documents[DocumentKind::RiskInformation->value] ?? null])));

        return [
            $this->roster(count($active), $aspirants),
            $this->conditions($places, count($active)),
            $this->fixedPlaces($places, count($active)),
            $this->policy($documents[DocumentKind::Policy->value] ?? null, $acks),
            $this->risks($documents[DocumentKind::RiskInformation->value] ?? null, $acks),
            $this->checklist($checklist, $places),
            $this->validation($places, count($active)),
            $this->periodic($checklist, $places),
            $this->document('5.6', 'Proceso de implementación del Teletrabajo', DocumentKind::ImplementationProcess, $documents),
            $this->equipment($workCenterId, $active),
            $this->document('5.8', 'Programa de mantenimiento de los equipos TIC', DocumentKind::MaintenanceProgram, $documents),
            $this->trainings($workCenterId, $active),
            $this->document('5.10', 'Mecanismo de reversibilidad', DocumentKind::Reversibility, $documents),
            $this->notices($workCenterId),
            $this->committee($workCenterId),
            $this->document('5.13', 'Mecanismo de atención de la violencia familiar', DocumentKind::FamilyViolence, $documents),
            $this->document('5.14', 'Apoyo para participar en las comisiones', DocumentKind::CommitteeParticipation, $documents),
        ];
    }

    /**
     * What the Comisión decided about the workplaces of the active people, counted.
     *
     * @return array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int}
     */
    private function places(string $workCenterId, int $active): array
    {
        $counts = ['total' => 0, 'inForce' => 0, 'decided' => 0, 'lapsed' => 0, 'notApt' => 0, 'peopleInForce' => 0];

        if ($active === 0) {
            return $counts;
        }

        $workplaces = Workplace::query()->whereNull('retired_at')
            ->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->where('status', TeleworkerStatus::Active)->select('id'))
            ->get(['id', 'teleworker_id']);
        $reviews = $this->standing->latestReviews(array_values($workplaces->map(fn (Workplace $workplace): string => $workplace->id)->all()));
        $months = $this->settings->periodMonths();
        $withOne = [];

        foreach ($workplaces as $workplace) {
            $review = $reviews[$workplace->id] ?? null;
            $status = $this->standing->statusOf($review, fn (): int => $months);
            $counts['total']++;
            $counts['decided'] += $review === null ? 0 : 1;
            $counts['notApt'] += $status === WorkplaceStatus::NotValid ? 1 : 0;
            $counts['lapsed'] += $review !== null && $review->decision->validates() && $status === WorkplaceStatus::Pending ? 1 : 0;

            if ($status === WorkplaceStatus::Valid || $status === WorkplaceStatus::ValidWithPlan) {
                $counts['inForce']++;
                $withOne[$workplace->teleworker_id] = true;
            }
        }

        $counts['peopleInForce'] = count($withOne);

        return $counts;
    }

    /** @return array<string, TeleworkDocument> by kind */
    private function documents(string $workCenterId): array
    {
        $documents = [];

        foreach (TeleworkDocument::query()->inForce()->where('work_center_id', $workCenterId)->get() as $document) {
            $documents[$document->kind->value] = $document;
        }

        return $documents;
    }

    private function roster(int $active, int $aspirants): ComplianceRow
    {
        $label = 'Listado de personas teletrabajadoras';

        return match (true) {
            $active > 0 => new ComplianceRow('5.1', $label, RowState::Done, trans_choice('{1} :count persona en el padrón.|[2,*] :count personas en el padrón.', $active)),
            $aspirants > 0 => new ComplianceRow('5.1', $label, RowState::Missing, trans_choice('{1} Hay :count aspirante, pero ninguna persona en el padrón.|[2,*] Hay :count aspirantes, pero ninguna persona en el padrón.', $aspirants)),
            default => new ComplianceRow('5.1', $label, RowState::NotApplicable, 'Nadie teletrabaja en este centro todavía.'),
        };
    }

    /** @param array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int} $places */
    private function conditions(array $places, int $active): ComplianceRow
    {
        $label = 'Conectividad y condiciones de seguridad de cada lugar';

        return match (true) {
            $active === 0 => $this->notApplicable('5.1.1', $label),
            $places['total'] === 0 => new ComplianceRow('5.1.1', $label, RowState::Missing, 'Ninguna persona tiene registrado un lugar de trabajo.'),
            default => new ComplianceRow('5.1.1', $label, $this->share($places['inForce'], $places['total']), trans_choice('{1} :good de :count lugar validado y vigente.|[2,*] :good de :count lugares validados y vigentes.', $places['total'], ['good' => $places['inForce']])),
        };
    }

    /** @param array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int} $places */
    private function fixedPlaces(array $places, int $active): ComplianceRow
    {
        $label = 'Lugares de trabajo fijos y convenidos';

        return $active === 0
            ? $this->notApplicable('5.1.2', $label)
            : new ComplianceRow('5.1.2', $label, $this->share($places['peopleInForce'], $active), trans_choice('{1} :good de :count persona con un lugar validado y vigente.|[2,*] :good de :count personas con un lugar validado y vigente.', $active, ['good' => $places['peopleInForce']]));
    }

    /** @param array<string, array{acknowledged: int, expected: int}> $acks */
    private function policy(?TeleworkDocument $document, array $acks): ComplianceRow
    {
        $label = 'Política de Teletrabajo';

        if ($document === null) {
            return new ComplianceRow('5.2', $label, RowState::Missing, 'Aún no subes la Política de Teletrabajo.');
        }

        $lacking = $this->lacking($document);
        $ack = $acks[$document->id] ?? ['acknowledged' => 0, 'expected' => 0];
        $parts = [
            "Versión {$document->version}",
            ...($lacking === [] ? [] : [$this->lackingText($lacking)]),
            trans_choice('{1} :good de :count persona confirmó que la leyó|[2,*] :good de :count personas confirmaron que la leyeron', max(1, $ack['expected']), ['good' => $ack['acknowledged']]),
        ];

        return new ComplianceRow('5.2', $label, $lacking === [] ? RowState::Done : RowState::Partial, implode('. ', $parts).'.');
    }

    /** @param array<string, array{acknowledged: int, expected: int}> $acks */
    private function risks(?TeleworkDocument $document, array $acks): ComplianceRow
    {
        $label = 'Información sobre los riesgos del Teletrabajo';

        if ($document === null) {
            return new ComplianceRow('5.3', $label, RowState::Missing, 'Aún no subes la información sobre los riesgos del Teletrabajo.');
        }

        $ack = $acks[$document->id] ?? ['acknowledged' => 0, 'expected' => 0];

        return match (true) {
            $ack['expected'] === 0 => new ComplianceRow('5.3', $label, RowState::Done, 'Aún no hay personas ni integrantes de la Comisión a quienes informar.'),
            $ack['acknowledged'] >= $ack['expected'] => new ComplianceRow('5.3', $label, RowState::Done, "{$ack['expected']} de {$ack['expected']} confirmaron que lo leyeron."),
            default => new ComplianceRow('5.3', $label, RowState::Partial, "{$ack['acknowledged']} de {$ack['expected']} confirmaron que lo leyeron."),
        };
    }

    /** @param array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int} $places */
    private function checklist(?Checklist $checklist, array $places): ComplianceRow
    {
        $label = 'Lista de verificación validada por la Comisión';

        return match (true) {
            $checklist === null => new ComplianceRow('5.4 y 7', $label, RowState::Missing, 'La Comisión aún no publica la lista de verificación del centro.'),
            $places['total'] === 0 => new ComplianceRow('5.4 y 7', $label, RowState::Done, "Lista publicada (versión {$checklist->version}); no hay lugares por validar."),
            default => new ComplianceRow('5.4 y 7', $label, $this->share($places['decided'], $places['total']), trans_choice('{1} :good de :count lugar con decisión de la Comisión.|[2,*] :good de :count lugares con decisión de la Comisión.', $places['total'], ['good' => $places['decided']])),
        };
    }

    /** @param array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int} $places */
    private function validation(array $places, int $active): ComplianceRow
    {
        $label = 'Validación vigente de cada lugar';

        if ($active === 0) {
            return $this->notApplicable('5.5', $label);
        }

        if ($places['total'] === 0) {
            return new ComplianceRow('5.5', $label, RowState::Missing, 'Ninguna persona tiene registrado un lugar de trabajo.');
        }

        $detail = trans_choice('{1} :good de :count lugar con validación vigente de la Comisión.|[2,*] :good de :count lugares con validación vigente de la Comisión.', $places['total'], ['good' => $places['inForce']]);

        if ($places['notApt'] > 0) {
            $detail .= ' '.trans_choice('{1} :count no es apto: ahí no se teletrabaja.|[2,*] :count no son aptos: ahí no se teletrabaja.', $places['notApt']);
        }

        return new ComplianceRow('5.5', $label, $this->share($places['inForce'], $places['total']), $detail);
    }

    /** @param array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int} $places */
    private function periodic(?Checklist $checklist, array $places): ComplianceRow
    {
        $label = 'Segunda sección de la lista, aplicada cada periodo';

        return match (true) {
            $checklist === null => new ComplianceRow('5.5.1', $label, RowState::Missing, 'La Comisión aún no publica la lista de verificación del centro.'),
            ! ChecklistQuestion::query()->where('checklist_id', $checklist->id)->where('section', 2)->exists() => new ComplianceRow('5.5.1', $label, RowState::Partial, 'La lista publicada no tiene Sección II, la que contestan las personas cada periodo.'),
            $places['lapsed'] > 0 => new ComplianceRow('5.5.1', $label, RowState::Partial, trans_choice('{1} :count lugar tiene vencida su revisión periódica.|[2,*] :count lugares tienen vencida su revisión periódica.', $places['lapsed'])),
            default => new ComplianceRow('5.5.1', $label, RowState::Done, 'Sección II publicada y ninguna revisión periódica vencida.'),
        };
    }

    /** @param array<string, TeleworkDocument> $documents by kind */
    private function document(string $numeral, string $label, DocumentKind $kind, array $documents): ComplianceRow
    {
        $document = $documents[$kind->value] ?? null;

        if ($document === null) {
            return new ComplianceRow($numeral, $label, RowState::Missing, 'Aún no subes este documento.');
        }

        $lacking = $this->lacking($document);

        return $lacking === []
            ? new ComplianceRow($numeral, $label, RowState::Done, "Versión {$document->version}.")
            : new ComplianceRow($numeral, $label, RowState::Partial, "Versión {$document->version}. {$this->lackingText($lacking)}.");
    }

    /**
     * @param  list<Teleworker>  $active
     */
    private function equipment(string $workCenterId, array $active): ComplianceRow
    {
        $label = 'Equipo proporcionado a cada persona';

        if ($active === []) {
            return $this->notApplicable('5.7', $label);
        }

        $delivered = array_flip(EquipmentItem::query()->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->where('status', TeleworkerStatus::Active)->select('id'))->distinct()->get(['teleworker_id'])->map(fn (EquipmentItem $item): string => $item->teleworker_id)->all());
        $covered = count(array_filter($active, fn (Teleworker $person): bool => isset($delivered[$person->id]) || ($person->no_equipment_reason !== null && $person->no_equipment_reason !== '')));

        return new ComplianceRow('5.7', $label, $this->share($covered, count($active)), trans_choice('{1} :good de :count persona con equipo registrado o con la justificación de no recibirlo.|[2,*] :good de :count personas con equipo registrado o con la justificación de no recibirlo.', count($active), ['good' => $covered]));
    }

    /**
     * @param  list<Teleworker>  $active
     */
    private function trainings(string $workCenterId, array $active): ComplianceRow
    {
        $label = 'Capacitación al menos una vez al año';

        if ($active === []) {
            return $this->notApplicable('5.9 y 8', $label);
        }

        if (! Training::query()->where('work_center_id', $workCenterId)->exists()) {
            return new ComplianceRow('5.9 y 8', $label, RowState::Missing, 'Aún no se registra ninguna capacitación en este centro.');
        }

        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $standings = array_map(fn (array $row): TrainingStanding => $this->training->standing($row['due'], $today), $this->training->schedule($active));
        $overdue = count(array_filter($standings, fn (TrainingStanding $standing): bool => $standing === TrainingStanding::Overdue));
        $soon = count(array_filter($standings, fn (TrainingStanding $standing): bool => $standing === TrainingStanding::DueSoon));

        if ($overdue > 0) {
            return new ComplianceRow('5.9 y 8', $label, RowState::Partial, trans_choice('{1} :count persona tiene su capacitación vencida (más de 12 meses).|[2,*] :count personas tienen su capacitación vencida (más de 12 meses).', $overdue));
        }

        return new ComplianceRow('5.9 y 8', $label, RowState::Done, $soon === 0 ? 'Todas las personas están al corriente.' : trans_choice('{1} :count persona debe capacitarse en el próximo mes.|[2,*] :count personas deben capacitarse en el próximo mes.', $soon));
    }

    private function notices(string $workCenterId): ComplianceRow
    {
        $label = 'Seguimiento de los avisos de riesgo de trabajo';
        $exams = 'Los exámenes médicos del 5.11 no los cubre Humetría: llévalos por separado.';
        $query = WorkerNotice::query()->where('kind', NoticeKind::WorkRisk)->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->select('id'));
        $total = (clone $query)->count();

        if ($total === 0) {
            return new ComplianceRow('5.11', $label, RowState::NotApplicable, "Nadie ha avisado de un riesgo de trabajo. {$exams}");
        }

        $reviewed = $query->whereNotNull('reviewed_at')->count();

        return new ComplianceRow('5.11', $label, $this->share($reviewed, $total), trans_choice('{1} :good de :count aviso de riesgo de trabajo revisado.|[2,*] :good de :count avisos de riesgo de trabajo revisados.', $total, ['good' => $reviewed])." {$exams}");
    }

    private function committee(string $workCenterId): ComplianceRow
    {
        $label = 'Comisión de seguridad e higiene con acceso a la documentación';

        if (! SafetyCommittee::query()->where('work_center_id', $workCenterId)->exists()) {
            return new ComplianceRow('5.12', $label, RowState::Missing, 'Registra la Comisión de seguridad e higiene del centro.');
        }

        $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());
        $members = CommitteeAssignment::query()->where('work_center_id', $workCenterId)->whereIn('user_id', $reviewers)->count();

        return $members === 0
            ? new ComplianceRow('5.12', $label, RowState::Partial, 'La Comisión está registrada, pero sin integrantes con acceso.')
            : new ComplianceRow('5.12', $label, RowState::Done, trans_choice('{1} :count integrante con acceso al expediente.|[2,*] :count integrantes con acceso al expediente.', $members));
    }

    private function notApplicable(string $numeral, string $label): ComplianceRow
    {
        return new ComplianceRow($numeral, $label, RowState::NotApplicable, 'Nadie teletrabaja en este centro todavía.');
    }

    private function share(int $good, int $total): RowState
    {
        return match (true) {
            $good >= $total => RowState::Done,
            $good === 0 => RowState::Missing,
            default => RowState::Partial,
        };
    }

    /** @return list<string> the letters of the document's items it does not cover. */
    private function lacking(TeleworkDocument $document): array
    {
        return array_values(array_diff($document->kind->coverableItems(), $document->covered_items));
    }

    /** @param list<string> $letters */
    private function lackingText(array $letters): string
    {
        return trans_choice('{1} Falta el inciso :items|[2,*] Faltan los incisos :items', count($letters), ['items' => implode(', ', $letters)]);
    }
}
