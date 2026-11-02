<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Instruments\Data\ConditionDefinition;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * What the assessment pages send to the browser: the people of a center with the state of their assessment, and one
 * assessment with its facts, conditions and result. Only who may assess reaches these pages (the viewer gets a 403).
 */
final readonly class AssessmentPages
{
    private const int PEOPLE_PER_PAGE = 15;

    public function __construct(private Roster $roster, private SeatingAssessment $instrument, private AssessmentSnapshot $snapshot, private AssessmentAccess $access) {}

    /**
     * @return array<string, mixed>
     */
    public function index(WorkCenterSummary $center, int $userId, string $search): array
    {
        $positions = StandingPosition::query()->where('work_center_id', $center->id)->get(['id', 'name', 'nature_allows']);
        $assignments = PositionAssignment::query()->whereIn('position_id', array_values($positions->map(fn (StandingPosition $position): string => $position->id)->all()))->whereNull('ends_on');

        if ($search !== '') {
            $assignments->whereIn('employee_id', $this->roster->matchingIds(new RosterQuery(activeInWorkCenter: $center->id, search: $search)));
        }

        $page = $assignments->orderBy('starts_on')->orderBy('id')->paginate(self::PEOPLE_PER_PAGE)->withQueryString();
        $ids = array_values(array_map(fn (PositionAssignment $assignment): string => $assignment->employee_id, $page->items()));
        $people = $this->roster->findMany($ids);
        $names = [];
        foreach ($positions as $position) {
            $names[$position->id] = $position->name;
        }
        $assessments = $this->assessmentsOf($ids);

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'people' => $page->through(fn (PositionAssignment $assignment): array => $this->row($assignment, $people, $names, $assessments)),
            'search' => $search,
            'canAssess' => $this->access->canAssess($userId, $center->id),
            'canManage' => Gate::allows('manage-seating'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(RiskAssessment $assessment, int $userId): array
    {
        $signed = $assessment->status === AssessmentStatus::Signed;
        $facts = $signed && $assessment->snapshot !== null ? $assessment->snapshot : $this->snapshot->capture($assessment, null);
        $level = $assessment->level;

        return [
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status->value,
                'statusLabel' => $assessment->status->label(),
                'workCenterId' => $assessment->work_center_id,
                'scores' => $assessment->scores,
                'verifiedOn' => $assessment->verified_on?->toDateString(),
                'otherAspects' => $assessment->other_aspects,
                'total' => $assessment->total,
                'level' => $level?->value,
                'levelLabel' => $level?->label(),
                'signedOn' => $assessment->signed_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
                'reassessmentDue' => $assessment->reassessment_due,
                'personProvidedHealthData' => $assessment->person_provided_health_data,
                'version' => $assessment->instrument_version,
            ],
            'facts' => $this->snapshot->facts($facts, $assessment->verified_on, $assessment->other_aspects),
            'conditions' => array_map(fn (ConditionDefinition $condition): array => [
                'key' => $condition->key,
                'letter' => $condition->letter,
                'title' => $condition->title,
                'options' => array_map(fn (int $points, string $text): array => ['points' => $points, 'text' => $text], array_keys($condition->options), array_values($condition->options)),
            ], $this->instrument->definition()->conditions),
            'suggestions' => $level === null ? null : ['label' => SuggestedMeasures::LABEL, 'measures' => array_map(fn ($kind): array => ['value' => $kind->value, 'label' => $kind->label()], SuggestedMeasures::for($level))],
            'canSign' => ! $signed && $this->access->canAssess($userId, $assessment->work_center_id),
            'canEdit' => ! $signed && $this->access->canAssess($userId, $assessment->work_center_id),
            'referrals' => $this->referrals($assessment->employee_id),
            'canRefer' => Gate::allows('manage-seating') || $this->access->canAssess($userId, $assessment->work_center_id),
            'canResend' => $signed && Gate::allows('manage-seating'),
        ];
    }

    /**
     * The latest referrals of the person (a short summary: a person has few).
     *
     * @return list<array{id: string, referred: bool, referredOn: string, note: string|null}>
     */
    private function referrals(string $employeeId): array
    {
        return array_values(MedicalReferral::query()->where('employee_id', $employeeId)->orderByDesc('referred_on')->orderByDesc('id')->limit(10)->get()
            ->map(fn (MedicalReferral $referral): array => ['id' => $referral->id, 'referred' => $referral->referred, 'referredOn' => $referral->referred_on->toDateString(), 'note' => $referral->note])->all());
    }

    /**
     * @param  list<string>  $employeeIds
     * @return array<string, array{draft: RiskAssessment|null, signed: RiskAssessment|null}>
     */
    private function assessmentsOf(array $employeeIds): array
    {
        $by = [];

        foreach (RiskAssessment::query()->whereIn('employee_id', $employeeIds)->orderBy('signed_at')->get(['id', 'employee_id', 'status', 'level', 'signed_at', 'reassessment_due']) as $assessment) {
            $by[$assessment->employee_id] ??= ['draft' => null, 'signed' => null];
            $by[$assessment->employee_id][$assessment->status === AssessmentStatus::Draft ? 'draft' : 'signed'] = $assessment;
        }

        return $by;
    }

    /**
     * @param  array<string, \App\Modules\Organization\Data\RosterEntry>  $people
     * @param  array<string, string>  $positionNames
     * @param  array<string, array{draft: RiskAssessment|null, signed: RiskAssessment|null}>  $assessments
     * @return array{employeeId: string, name: string, positionName: string, state: string, stateLabel: string, level: string|null, levelLabel: string|null, signedOn: string|null, assessmentId: string|null}
     */
    private function row(PositionAssignment $assignment, array $people, array $positionNames, array $assessments): array
    {
        $mine = $assessments[$assignment->employee_id] ?? ['draft' => null, 'signed' => null];
        $signed = $mine['signed'];
        $due = $signed !== null && ($signed->reassessment_due || $signed->signed_at === null || $this->overdue($signed->signed_at));
        [$state, $label] = match (true) {
            $mine['draft'] !== null => ['draft', 'Borrador'],
            $signed === null => ['none', 'Sin evaluar'],
            $due => ['due', 'Por reevaluar'],
            default => ['signed', 'Vigente'],
        };

        return [
            'employeeId' => $assignment->employee_id,
            'name' => array_key_exists($assignment->employee_id, $people) ? $people[$assignment->employee_id]->name : '',
            'positionName' => $positionNames[$assignment->position_id] ?? '',
            'state' => $state,
            'stateLabel' => $label,
            'level' => $signed?->level?->value,
            'levelLabel' => $signed?->level?->label(),
            'signedOn' => $signed?->signed_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            'assessmentId' => ($mine['draft'] ?? $signed)?->id,
        ];
    }

    private function overdue(CarbonImmutable $signedAt): bool
    {
        return $signedAt->addMonths(config()->integer('seating.reassessment_months'))->isPast();
    }
}
