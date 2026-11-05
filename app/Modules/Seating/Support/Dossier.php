<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Campaigns\Contracts\CenterSurveys;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Tenancy\Data\WorkCenterProfile;
use Illuminate\Support\Facades\Gate;

/**
 * What the printable dossier («expediente») of a work center sends to the browser: one section per requirement with what
 * exists, with its date, or «Falta»; the distribution of risk levels without names; the documents; and the links back into
 * the application. The dossier may leave the system on paper, so it carries dates, states and counts, and never the
 * assessments of a person, the photos, the referrals or the answers of the survey: those are only counted, with a link
 * (criteria C-17 and C-18). It is built whole, in a fixed number of queries whatever the positions, people and seats.
 */
final readonly class Dossier
{
    public function __construct(private CenterBoard $board, private Committee $committee, private CenterSurveys $surveys) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WorkCenterProfile $center, string $generatedBy, bool $readyOnly = false): array
    {
        $id = $center->id;
        $signed = RiskAssessment::query()->where('work_center_id', $id)->where('status', AssessmentStatus::Signed)->orderBy('signed_at')->orderBy('id')->get(['employee_id', 'level', 'signed_at', 'recorded_in_document_id']);
        $positions = StandingPosition::query()->where('work_center_id', $id)->orderBy('name')->orderBy('id')->get(['id', 'name', 'placement', 'nature_allows', 'nature_reason']);
        $documents = SeatingDocument::query()->where('work_center_id', $id)->orderBy('document_on')->orderBy('id')->get(['kind', 'title', 'document_on', 'incisos']);
        $facts = new DossierFacts(
            SeatingProfile::query()->where('work_center_id', $id)->first(['sector', 'no_standing_positions', 'declared_on']),
            $positions,
            Seat::query()->where('work_center_id', $id)->orderBy('created_at')->orderBy('id')->get(['position_id', 'kind', 'use', 'quantity', 'condition', 'signposted', 'location', 'photo_path']),
            $signed,
            PreventiveMeasure::query()->where('work_center_id', $id)->where('status', '!=', MeasureStatus::Cancelled)->orderBy('due_on')->orderBy('id')->get(['kind', 'status', 'due_on']),
            PauseAgreement::query()->where('work_center_id', $id)->orderBy('agreed_on')->orderBy('id')->get(['position_id', 'pauses_per_shift', 'minutes_each', 'agreed_on']),
            InternalRegulation::query()->where('work_center_id', $id)->first(['status', 'signed_on', 'deposited_on', 'deposit_folio', 'distributed_on']),
            $documents,
            count($this->committee->userIdsOf($id)),
        );
        $board = $this->board->for($id)['requirements'];

        return [
            'workCenterId' => $id,
            'header' => ['legalName' => $center->legalName, 'rfc' => $center->rfc, 'workCenter' => $center->name, 'address' => $center->address, 'generatedOn' => Today::date(), 'generatedBy' => $generatedBy],
            'rows' => array_map(fn (array $requirement): array => [
                'key' => $requirement['key'],
                'number' => $requirement['number'],
                'label' => $requirement['label'],
                'state' => $requirement['state'],
                'stateLabel' => $requirement['stateLabel'],
                'lines' => DossierLines::of($requirement['key'], $requirement['state'], $requirement['count'], $facts),
            ], array_values(array_filter($board, fn (array $requirement): bool => ! $readyOnly || $requirement['state'] === RowState::Met->value))),
            'levels' => $this->levels($signed->all()),
            'documents' => array_values($documents->map(fn (SeatingDocument $document): array => [
                'kindLabel' => $document->kind->label(),
                'title' => $document->title,
                'documentOn' => $document->document_on->toDateString(),
                'incisos' => $document->incisos,
            ])->all()),
            'links' => $this->links($id, $signed->count(), $facts->seats->filter(fn (Seat $seat): bool => $seat->photo_path !== null)->count()),
            'fineNote' => sprintf(
                'La LFT no fija una multa propia para esta obligación; las multas aplicables van de %s a %s UMA por persona afectada, y se duplican si reinciden. Este expediente cuenta lo registrado en Humetría: no afirma qué multa aplicaría la autoridad.',
                number_format(config()->integer('seating.fine_uma_min')),
                number_format(config()->integer('seating.fine_uma_max')),
            ),
        ];
    }

    /**
     * The latest signed assessment of each person, counted by level; no names.
     *
     * @param  array<int, RiskAssessment>  $signed  oldest first
     * @return array{low: int, medium: int, high: int}
     */
    private function levels(array $signed): array
    {
        $current = [];

        foreach ($signed as $assessment) {
            $current[$assessment->employee_id] = $assessment->level;
        }

        $count = fn (RiskLevel $level): int => count(array_filter($current, fn (?RiskLevel $found): bool => $found === $level));

        return ['low' => $count(RiskLevel::Low), 'medium' => $count(RiskLevel::Medium), 'high' => $count(RiskLevel::High)];
    }

    /**
     * What stays inside the application, with how many there are and whether the person can open it.
     *
     * @return list<array{key: string, label: string, count: int, open: bool}>
     */
    private function links(string $workCenterId, int $assessments, int $photos): array
    {
        $assess = Gate::allows('assess-seating');

        return [
            ['key' => 'people', 'label' => 'Evaluaciones por persona', 'count' => $assessments, 'open' => $assess],
            ['key' => 'inventory', 'label' => 'Fotos de los asientos', 'count' => $photos, 'open' => true],
            ['key' => 'people', 'label' => 'Canalizaciones', 'count' => MedicalReferral::query()->where('work_center_id', $workCenterId)->count(), 'open' => $assess],
            ['key' => 'survey', 'label' => 'Respuestas de la encuesta', 'count' => $this->surveys->forCenter($workCenterId)[0]->answered ?? 0, 'open' => Gate::allows('manage-seating')],
        ];
    }
}
