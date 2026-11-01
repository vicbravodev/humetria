<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
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
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where each row of the inspection table 10.3 (DOF 08/06/2023) stands in a work center (criterio C-30), in the table's
 * order. A row is `done` when the evidence it asks for is there, `partial` when only part of it is, `missing` when none
 * is and `not_applicable` when the center has nobody it could apply to. Read in a fixed number of queries whatever the
 * number of people, workplaces, documents or centers (`forMany`).
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
 * - 5.4 y 7 Lista de verificación: a published list whose Sección I covers every requirement of 5.1.1 and chapter 7
 *   (`Coverage`), and every workplace with a decision of the Comisión (any decision, even one that lapsed: the
 *   validation was made).
 * - 5.5 Validación: as 5.1.1, from the Comisión's side; the detail says how many workplaces are not apt, where nobody
 *   teleworks.
 * - 5.5.1 Segunda versión periódica: a published list whose Sección II exists, covers every requirement, and no
 *   validation lapsed (periodic review overdue).
 * - 5.6 Proceso de implementación: the document; partial until it covers the incisos a) to d).
 * - 5.7 Equipo: the active people whose equipment the person confirmed (received, or refused with its reason: criterio
 *   C-22) or who have the justification of having none. Equipment registered but not yet answered is «sin acuse»: the
 *   row is partial, not missing, while some wait for the person.
 * - 5.8, 5.10, 5.13, 5.14: the document of that kind in force.
 * - 5.9 y 8 Capacitación, by people (`TrainingDue`): not applicable without active people; done when nobody is past twelve
 *   months since their last training in their period (a person who never trained is within their first twelve months);
 *   partial when some are overdue; missing when everyone is.
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
        private Coverage $coverage,
    ) {}

    /** @return list<ComplianceRow> */
    public function for(string $workCenterId): array
    {
        return $this->forMany([$workCenterId])[$workCenterId] ?? [];
    }

    /**
     * The rows of several centers in a fixed number of queries whatever their number: each kind of evidence is read once
     * for all of them and split by center.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, list<ComplianceRow>> by center id, in the order asked
     */
    public function forMany(array $workCenterIds): array
    {
        // A repeated id yields the same rows under the same key, and the keys of $ids are never read: both are equivalent.
        $ids = array_values(array_unique($workCenterIds)); // @pest-mutate-ignore: UnwrapArrayUnique,UnwrapArrayValues

        if ($ids === []) {
            return [];
        }

        $people = $this->people($ids);
        $places = $this->places($ids, $people);
        $documents = $this->documents($ids);
        [$checklists, $sectionTwo, $uncovered] = $this->checklists($ids);
        $acks = $this->acknowledgements->forDocuments($this->acknowledgeable($documents));
        $equipment = $this->equipment($ids, $people);
        $trainings = $this->trainings($people);
        $notices = $this->notices($ids);
        [$committees, $members] = $this->committees($ids);
        $rows = [];

        // The defaults below stand for a center without active people, and every row that reads equipment, training or the
        // reviewed notices answers before it reads them when there is nobody (or no notice): changing them changes nothing.
        foreach ($ids as $id) {
            $checklist = $checklists[$id] ?? null;
            $rows[$id] = $this->rows(new CenterFacts(
                count($people['active'][$id] ?? []),
                $people['aspirants'][$id] ?? 0, // @pest-mutate-ignore: DecrementInteger
                $places[$id],
                $documents[$id] ?? [],
                $checklist,
                $checklist !== null && isset($sectionTwo[$checklist->id]),
                $checklist === null ? [] : $uncovered[$checklist->id] ?? [],
                $acks,
                $equipment[$id]['covered'] ?? 0, // @pest-mutate-ignore: IncrementInteger,DecrementInteger
                $equipment[$id]['unconfirmed'] ?? 0, // @pest-mutate-ignore: IncrementInteger,DecrementInteger
                $trainings[$id]['overdue'] ?? 0, // @pest-mutate-ignore: IncrementInteger,DecrementInteger
                $trainings[$id]['soon'] ?? 0, // @pest-mutate-ignore: IncrementInteger,DecrementInteger
                $notices[$id]['total'] ?? 0,
                $notices[$id]['reviewed'] ?? 0, // @pest-mutate-ignore: IncrementInteger,DecrementInteger
                isset($committees[$id]),
                $members[$id] ?? 0,
            ));
        }

        return $rows;
    }

    /**
     * The policy and the risk information of every center: the documents people confirm.
     *
     * @param  array<string, array<string, TeleworkDocument>>  $documents
     * @return list<TeleworkDocument>
     */
    private function acknowledgeable(array $documents): array
    {
        $found = [];

        foreach ($documents as $kinds) {
            foreach ([DocumentKind::Policy, DocumentKind::RiskInformation] as $kind) {
                if (isset($kinds[$kind->value])) {
                    $found[] = $kinds[$kind->value];
                }
            }
        }

        return $found;
    }

    /** @return list<ComplianceRow> */
    private function rows(CenterFacts $facts): array
    {
        $policy = $facts->documents[DocumentKind::Policy->value] ?? null;
        $risk = $facts->documents[DocumentKind::RiskInformation->value] ?? null;

        return [
            $this->roster($facts->active, $facts->aspirants),
            $this->conditions($facts),
            $this->fixedPlaces($facts),
            $this->policy($policy, $facts->acks),
            $this->risks($risk, $facts->acks),
            $this->checklist($facts),
            $this->validation($facts),
            $this->periodic($facts),
            $this->document('5.6', 'Proceso de implementación del Teletrabajo', DocumentKind::ImplementationProcess, $facts->documents),
            $this->equipmentRow($facts),
            $this->document('5.8', 'Programa de mantenimiento de los equipos TIC', DocumentKind::MaintenanceProgram, $facts->documents),
            $this->trainingRow($facts),
            $this->document('5.10', 'Mecanismo de reversibilidad', DocumentKind::Reversibility, $facts->documents),
            $this->noticesRow($facts),
            $this->committeeRow($facts),
            $this->document('5.13', 'Mecanismo de atención de la violencia familiar', DocumentKind::FamilyViolence, $facts->documents),
            $this->document('5.14', 'Apoyo para participar en las comisiones', DocumentKind::CommitteeParticipation, $facts->documents),
        ];
    }

    /**
     * The active and aspiring people of the centers, by center.
     *
     * @param  list<string>  $ids
     * @return array{active: array<string, list<Teleworker>>, aspirants: array<string, int>}
     */
    private function people(array $ids): array
    {
        // Without the aspirants key, the first aspirant creates it and a center without any falls back to 0: equivalent.
        $people = [
            'active' => [],
            'aspirants' => [], // @pest-mutate-ignore: RemoveArrayItem
        ];

        foreach (Teleworker::query()->whereIn('work_center_id', $ids)->whereIn('status', [TeleworkerStatus::Active, TeleworkerStatus::Aspirant])
            ->get(['id', 'work_center_id', 'status', 'started_at', 'no_equipment_reason']) as $person) {
            if ($person->status === TeleworkerStatus::Active) {
                $people['active'][$person->work_center_id][] = $person;
            } else {
                $people['aspirants'][$person->work_center_id] = ($people['aspirants'][$person->work_center_id] ?? 0) + 1;
            }
        }

        return $people;
    }

    /**
     * The active people of these centers, as a subquery of their ids.
     *
     * @param  list<string>  $ids
     * @return Builder<Teleworker>
     */
    private function activeIds(array $ids): Builder
    {
        return Teleworker::query()->whereIn('work_center_id', $ids)->where('status', TeleworkerStatus::Active)->select('id');
    }

    /**
     * What the Comisión decided about the workplaces of the active people, counted by center.
     *
     * @param  list<string>  $ids
     * @param  array{active: array<string, list<Teleworker>>, aspirants: array<string, int>}  $people
     * @return array<string, array{total: int, inForce: int, decided: int, lapsed: int, notApt: int, peopleInForce: int}>
     */
    private function places(array $ids, array $people): array
    {
        $centerOf = [];

        foreach ($people['active'] as $center => $list) {
            foreach ($list as $person) {
                $centerOf[$person->id] = $center;
            }
        }

        $total = $inForce = $decided = $lapsed = $notApt = $peopleInForce = array_fill_keys($ids, 0);

        if ($centerOf !== []) {
            $workplaces = Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', $this->activeIds($ids))->get(['id', 'teleworker_id']);
            // latestReviews only chunks the ids into a whereIn: their keys are never read.
            $reviews = $this->standing->latestReviews(array_values($workplaces->map(fn (Workplace $workplace): string => $workplace->id)->all())); // @pest-mutate-ignore: UnwrapArrayValues
            $months = $this->settings->periodMonths();
            $withOne = [];

            foreach ($workplaces as $workplace) {
                $center = $centerOf[$workplace->teleworker_id] ?? null;

                if ($center === null) {
                    continue;
                }

                $review = $reviews[$workplace->id] ?? null;
                $status = $this->standing->statusOf($review, fn (): int => $months);
                $total[$center]++;
                $decided[$center] += $review === null ? 0 : 1;
                $notApt[$center] += $status === WorkplaceStatus::NotValid ? 1 : 0;
                $lapsed[$center] += $review !== null && $review->decision->validates() && $status === WorkplaceStatus::Pending ? 1 : 0;

                if ($status === WorkplaceStatus::Valid || $status === WorkplaceStatus::ValidWithPlan) {
                    $inForce[$center]++;

                    if (! isset($withOne[$workplace->teleworker_id])) {
                        // Here and in the other sets of this class, isset() reads only the key: the value true is never read.
                        $withOne[$workplace->teleworker_id] = true; // @pest-mutate-ignore: TrueToFalse
                        $peopleInForce[$center]++;
                    }
                }
            }
        }

        $counts = [];

        foreach ($ids as $id) {
            $counts[$id] = ['total' => $total[$id], 'inForce' => $inForce[$id], 'decided' => $decided[$id], 'lapsed' => $lapsed[$id], 'notApt' => $notApt[$id], 'peopleInForce' => $peopleInForce[$id]];
        }

        return $counts;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array<string, TeleworkDocument>> by center, then kind
     */
    private function documents(array $ids): array
    {
        $documents = [];

        foreach (TeleworkDocument::query()->inForce()->whereIn('work_center_id', $ids)->get() as $document) {
            $documents[$document->work_center_id][$document->kind->value] = $document;
        }

        return $documents;
    }

    /**
     * The newest published list of each center, which of them have a Sección II, and the requirements each leaves uncovered.
     *
     * @param  list<string>  $ids
     * @return array{0: array<string, Checklist>, 1: array<string, true>, 2: array<string, array<int, list<Requirement>>>}
     */
    private function checklists(array $ids): array
    {
        $latest = [];

        foreach (Checklist::query()->whereIn('work_center_id', $ids)->where('status', ChecklistStatus::Published)->orderByDesc('version')->get() as $checklist) {
            $latest[$checklist->work_center_id] ??= $checklist;
        }

        if ($latest === []) {
            return [[], [], []];
        }

        $sectionTwo = [];

        foreach (ChecklistQuestion::query()->whereIn('checklist_id', array_map(fn (Checklist $checklist): string => $checklist->id, $latest))->where('section', 2)->distinct()->get(['checklist_id']) as $question) {
            $sectionTwo[$question->checklist_id] = true; // @pest-mutate-ignore: TrueToFalse
        }

        // missingMany only iterates the lists and keys its answer by list id: the keys of $latest are never read.
        return [$latest, $sectionTwo, $this->coverage->missingMany(array_values($latest))]; // @pest-mutate-ignore: UnwrapArrayValues
    }

    /**
     * Per center: the active people whose equipment the person confirmed (or who have the reason for having none), and
     * those whose only equipment still waits for the person.
     *
     * @param  list<string>  $ids
     * @param  array{active: array<string, list<Teleworker>>, aspirants: array<string, int>}  $people
     * @return array<string, array{covered: int, unconfirmed: int}>
     */
    private function equipment(array $ids, array $people): array
    {
        if ($people['active'] === []) {
            return [];
        }

        $answered = [];
        $waiting = [];

        foreach (EquipmentItem::query()->whereIn('teleworker_id', $this->activeIds($ids))->get(['teleworker_id', 'status']) as $item) {
            if ($item->status === EquipmentStatus::Pending) {
                $waiting[$item->teleworker_id] = true; // @pest-mutate-ignore: TrueToFalse
            } else {
                $answered[$item->teleworker_id] = true; // @pest-mutate-ignore: TrueToFalse
            }
        }

        $counts = [];

        foreach ($people['active'] as $center => $list) {
            $covered = 0;
            $unconfirmed = 0;

            foreach ($list as $person) {
                if (isset($answered[$person->id]) || ($person->no_equipment_reason !== null && $person->no_equipment_reason !== '')) {
                    $covered++;
                } elseif (isset($waiting[$person->id])) {
                    $unconfirmed++;
                }
            }

            $counts[$center] = ['covered' => $covered, 'unconfirmed' => $unconfirmed];
        }

        return $counts;
    }

    /**
     * Per center: the active people past twelve months since their last training, and those within the month before it.
     *
     * @param  array{active: array<string, list<Teleworker>>, aspirants: array<string, int>}  $people
     * @return array<string, array{overdue: int, soon: int}>
     */
    private function trainings(array $people): array
    {
        $everyone = [];

        foreach ($people['active'] as $list) {
            array_push($everyone, ...$list);
        }

        $schedule = $this->training->schedule($everyone);
        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $counts = [];

        foreach ($people['active'] as $center => $list) {
            $overdue = 0;
            $soon = 0;

            foreach ($list as $person) {
                $standing = $this->training->standing($schedule[$person->id]['due'], $today);
                $overdue += $standing === TrainingStanding::Overdue ? 1 : 0;
                $soon += $standing === TrainingStanding::DueSoon ? 1 : 0;
            }

            $counts[$center] = ['overdue' => $overdue, 'soon' => $soon];
        }

        return $counts;
    }

    /**
     * Per center: the notices of a risk of work, and how many were reviewed.
     *
     * @param  list<string>  $ids
     * @return array<string, array{total: int, reviewed: int}>
     */
    private function notices(array $ids): array
    {
        $notices = WorkerNotice::query()->where('kind', NoticeKind::WorkRisk)
            ->whereIn('teleworker_id', Teleworker::query()->whereIn('work_center_id', $ids)->select('id'))->get(['teleworker_id', 'reviewed_at']);

        if ($notices->isEmpty()) {
            return [];
        }

        $centerOf = [];

        // A whereIn ignores the keys of its values.
        foreach (Teleworker::query()->whereIn('id', array_values($notices->map(fn (WorkerNotice $notice): string => $notice->teleworker_id)->unique()->all()))->get(['id', 'work_center_id']) as $person) { // @pest-mutate-ignore: UnwrapArrayValues
            $centerOf[$person->id] = $person->work_center_id;
        }

        $counts = [];

        foreach ($notices as $notice) {
            $center = $centerOf[$notice->teleworker_id] ?? null;

            if ($center !== null) {
                $counts[$center] ??= ['total' => 0, 'reviewed' => 0];
                $counts[$center]['total']++;
                $counts[$center]['reviewed'] += $notice->reviewed_at === null ? 0 : 1;
            }
        }

        return $counts;
    }

    /**
     * The centers with a Comisión registered, and how many members of each still hold a reviewing role.
     *
     * @param  list<string>  $ids
     * @return array{0: array<string, true>, 1: array<string, int>}
     */
    private function committees(array $ids): array
    {
        $registered = [];

        foreach (SafetyCommittee::query()->whereIn('work_center_id', $ids)->get(['work_center_id']) as $committee) {
            $registered[$committee->work_center_id] = true; // @pest-mutate-ignore: TrueToFalse
        }

        $members = [];
        $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());

        if ($registered !== []) {
            foreach (CommitteeAssignment::query()->whereIn('work_center_id', $ids)->whereIn('user_id', $reviewers)->get(['work_center_id']) as $seat) {
                $members[$seat->work_center_id] = ($members[$seat->work_center_id] ?? 0) + 1;
            }
        }

        return [$registered, $members];
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

    private function conditions(CenterFacts $facts): ComplianceRow
    {
        $label = 'Conectividad y condiciones de seguridad de cada lugar';
        $places = $facts->places;

        return match (true) {
            $facts->active === 0 => $this->notApplicable('5.1.1', $label),
            $places['total'] === 0 => new ComplianceRow('5.1.1', $label, RowState::Missing, 'Ninguna persona tiene registrado un lugar de trabajo.'),
            default => new ComplianceRow('5.1.1', $label, $this->share($places['inForce'], $places['total']), trim(trans_choice('{1} :good de :count lugar validado y vigente.|[2,*] :good de :count lugares validados y vigentes.', $places['total'], ['good' => $places['inForce']]).' '.$this->notApt($places['notApt']))),
        };
    }

    private function fixedPlaces(CenterFacts $facts): ComplianceRow
    {
        $label = 'Lugares de trabajo fijos y convenidos';

        return $facts->active === 0
            ? $this->notApplicable('5.1.2', $label)
            : new ComplianceRow('5.1.2', $label, $this->share($facts->places['peopleInForce'], $facts->active), trans_choice('{1} :good de :count persona con un lugar validado y vigente.|[2,*] :good de :count personas con un lugar validado y vigente.', $facts->active, ['good' => $facts->places['peopleInForce']]));
    }

    /** What to do with a place nobody may telework from: it is not deleted, it is retired or the person goes back (C-13). */
    private function notApt(int $count): string
    {
        return $count === 0 ? '' : trans_choice('{1} :count lugar no es apto: retíralo o inicia la reversibilidad.|[2,*] :count lugares no son aptos: retíralos o inicia la reversibilidad.', $count);
    }

    /** @param array<string, array{acknowledged: int, expected: int}> $acks */
    private function policy(?TeleworkDocument $document, array $acks): ComplianceRow
    {
        $label = 'Política de Teletrabajo';

        if ($document === null) {
            return new ComplianceRow('5.2', $label, RowState::Missing, 'Aún no subes la Política de Teletrabajo.');
        }

        $lacking = $this->lacking($document);
        $ack = $acks[$document->id];
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

        $ack = $acks[$document->id];

        return match (true) {
            $ack['expected'] === 0 => new ComplianceRow('5.3', $label, RowState::Done, 'Aún no hay personas ni integrantes de la Comisión a quienes informar.'),
            $ack['acknowledged'] >= $ack['expected'] => new ComplianceRow('5.3', $label, RowState::Done, "{$ack['expected']} de {$ack['expected']} confirmaron que lo leyeron."),
            default => new ComplianceRow('5.3', $label, RowState::Partial, "{$ack['acknowledged']} de {$ack['expected']} confirmaron que lo leyeron."),
        };
    }

    private function checklist(CenterFacts $facts): ComplianceRow
    {
        $label = 'Lista de verificación validada por la Comisión';
        $places = $facts->places;

        if ($facts->checklist === null) {
            return new ComplianceRow('5.4 y 7', $label, RowState::Missing, 'La Comisión aún no publica la lista de verificación del centro.');
        }

        $lacking = $facts->uncovered[1] ?? [];
        $detail = $places['total'] === 0
            ? "Lista publicada (versión {$facts->checklist->version}); no hay lugares por validar."
            : trans_choice('{1} :good de :count lugar con decisión de la Comisión.|[2,*] :good de :count lugares con decisión de la Comisión.', $places['total'], ['good' => $places['decided']]);
        // With no places share(0, 0) is Done as well: `=== -1` changes nothing.
        $state = $places['total'] === 0 ? RowState::Done : $this->share($places['decided'], $places['total']); // @pest-mutate-ignore: DecrementInteger

        if ($lacking !== []) {
            $detail .= ' '.$this->uncoveredText(1, $lacking);
            $state = $state === RowState::Done ? RowState::Partial : $state;
        }

        return new ComplianceRow('5.4 y 7', $label, $state, $detail);
    }

    private function validation(CenterFacts $facts): ComplianceRow
    {
        $label = 'Validación vigente de cada lugar';
        $places = $facts->places;

        if ($facts->active === 0) {
            return $this->notApplicable('5.5', $label);
        }

        if ($places['total'] === 0) {
            return new ComplianceRow('5.5', $label, RowState::Missing, 'Ninguna persona tiene registrado un lugar de trabajo.');
        }

        $detail = trim(trans_choice('{1} :good de :count lugar con validación vigente de la Comisión.|[2,*] :good de :count lugares con validación vigente de la Comisión.', $places['total'], ['good' => $places['inForce']]).' '.$this->notApt($places['notApt']));

        return new ComplianceRow('5.5', $label, $this->share($places['inForce'], $places['total']), $detail);
    }

    private function periodic(CenterFacts $facts): ComplianceRow
    {
        $label = 'Segunda sección de la lista, aplicada cada periodo';
        $lacking = $facts->uncovered[2] ?? [];

        return match (true) {
            $facts->checklist === null => new ComplianceRow('5.5.1', $label, RowState::Missing, 'La Comisión aún no publica la lista de verificación del centro.'),
            ! $facts->sectionTwo => new ComplianceRow('5.5.1', $label, RowState::Partial, 'La lista publicada no tiene Sección II, la que contestan las personas cada periodo.'),
            // roman() calls every section but the first «II»: a 3 here reads the same.
            $lacking !== [] => new ComplianceRow('5.5.1', $label, RowState::Partial, $this->uncoveredText(2, $lacking)), // @pest-mutate-ignore: IncrementInteger
            $facts->places['lapsed'] > 0 => new ComplianceRow('5.5.1', $label, RowState::Partial, trans_choice('{1} :count lugar tiene vencida su revisión periódica.|[2,*] :count lugares tienen vencida su revisión periódica.', $facts->places['lapsed'])),
            default => new ComplianceRow('5.5.1', $label, RowState::Done, 'Sección II publicada y ninguna revisión periódica vencida.'),
        };
    }

    /**
     * @param  array<string, TeleworkDocument>  $documents  by kind
     */
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

    private function equipmentRow(CenterFacts $facts): ComplianceRow
    {
        $label = 'Equipo proporcionado a cada persona';

        if ($facts->active === 0) {
            return $this->notApplicable('5.7', $label);
        }

        $detail = trans_choice('{1} :good de :count persona con equipo confirmado o con la justificación de no recibirlo.|[2,*] :good de :count personas con equipo confirmado o con la justificación de no recibirlo.', $facts->active, ['good' => $facts->equipmentCovered]);

        if ($facts->equipmentUnconfirmed > 0) {
            $detail .= ' '.trans_choice('{1} :count persona con equipo sin acuse de recibido.|[2,*] :count personas con equipo sin acuse de recibido.', $facts->equipmentUnconfirmed);
        }

        $state = match (true) {
            $facts->equipmentCovered >= $facts->active => RowState::Done,
            $facts->equipmentCovered === 0 && $facts->equipmentUnconfirmed === 0 => RowState::Missing,
            default => RowState::Partial,
        };

        return new ComplianceRow('5.7', $label, $state, $detail);
    }

    private function trainingRow(CenterFacts $facts): ComplianceRow
    {
        $label = 'Capacitación al menos una vez al año';

        return match (true) {
            $facts->active === 0 => $this->notApplicable('5.9 y 8', $label),
            $facts->overdue >= $facts->active => new ComplianceRow('5.9 y 8', $label, RowState::Missing, trans_choice('{1} :count persona tiene su capacitación vencida (más de 12 meses).|[2,*] Las :count personas tienen su capacitación vencida (más de 12 meses).', $facts->overdue)),
            $facts->overdue > 0 => new ComplianceRow('5.9 y 8', $label, RowState::Partial, trans_choice('{1} :count persona tiene su capacitación vencida (más de 12 meses).|[2,*] :count personas tienen su capacitación vencida (más de 12 meses).', $facts->overdue)),
            default => new ComplianceRow('5.9 y 8', $label, RowState::Done, $facts->dueSoon === 0 ? 'Todas las personas están al corriente.' : trans_choice('{1} :count persona debe capacitarse en el próximo mes.|[2,*] :count personas deben capacitarse en el próximo mes.', $facts->dueSoon)),
        };
    }

    private function noticesRow(CenterFacts $facts): ComplianceRow
    {
        $label = 'Seguimiento de los avisos de riesgo de trabajo';
        $exams = 'Los exámenes médicos del 5.11 no los cubre Humetría: llévalos por separado.';

        if ($facts->noticeTotal === 0) {
            return new ComplianceRow('5.11', $label, RowState::NotApplicable, "Nadie ha avisado de un riesgo de trabajo. {$exams}");
        }

        return new ComplianceRow('5.11', $label, $this->share($facts->noticeReviewed, $facts->noticeTotal), trans_choice('{1} :good de :count aviso de riesgo de trabajo revisado.|[2,*] :good de :count avisos de riesgo de trabajo revisados.', $facts->noticeTotal, ['good' => $facts->noticeReviewed])." {$exams}");
    }

    private function committeeRow(CenterFacts $facts): ComplianceRow
    {
        $label = 'Comisión de seguridad e higiene con acceso a la documentación';

        return match (true) {
            ! $facts->committee => new ComplianceRow('5.12', $label, RowState::Missing, 'Registra la Comisión de seguridad e higiene del centro.'),
            $facts->members === 0 => new ComplianceRow('5.12', $label, RowState::Partial, 'La Comisión está registrada, pero sin integrantes con acceso.'),
            default => new ComplianceRow('5.12', $label, RowState::Done, trans_choice('{1} :count integrante con acceso al expediente.|[2,*] :count integrantes con acceso al expediente.', $facts->members)),
        };
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

    /**
     * @param  list<Requirement>  $requirements
     */
    private function uncoveredText(int $section, array $requirements): string
    {
        return "A la Sección {$this->roman($section)} le falta cubrir: ".implode(', ', array_map(fn (Requirement $requirement): string => $requirement->label(), $requirements)).'.';
    }

    private function roman(int $section): string
    {
        return $section === 1 ? 'I' : 'II';
    }

    /** @return list<string> the letters of the document's items it does not cover. */
    private function lacking(TeleworkDocument $document): array
    {
        // The letters are only compared with [] and joined: their keys are never read.
        return array_values(array_diff($document->kind->coverableItems(), $document->covered_items)); // @pest-mutate-ignore: UnwrapArrayValues
    }

    /** @param list<string> $letters */
    private function lackingText(array $letters): string
    {
        return trans_choice('{1} Falta el inciso :items|[2,*] Faltan los incisos :items', count($letters), ['items' => implode(', ', $letters)]);
    }
}
