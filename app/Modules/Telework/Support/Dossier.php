<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\TrainingStanding;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Data\WorkCenterProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;

/**
 * What the printable dossier («expediente») of a work center sends to the browser (criterio C-30): one block of evidence
 * per row of table 10.3, complete and in a fixed number of queries whatever the people and documents. The dossier may
 * leave the system on paper, so it carries dates, states, names and counts, and never the checklist answers, the risk
 * notes, the special needs, the photos, the notices' descriptions, the review notes, the refusal reasons or any IP: the
 * photos and the answers are only counted, with a link back into the application.
 *
 * Marital status, phone and home address (5.1) are for whoever manages NOM-037; the Comisión reads the rest (C-18).
 */
final readonly class Dossier
{
    private const int TRAINING_MONTHS = 12;

    public function __construct(
        private ComplianceRows $rows,
        private WorkCenters $workCenters,
        private Roster $roster,
        private WorkplaceStanding $standing,
        private Settings $settings,
        private Coverage $coverage,
        private AckProgress $acknowledgements,
        private TrainingDue $training,
        private Members $members,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(mixed $tenant, WorkCenterProfile $profile, string $generatedBy, bool $personal): array
    {
        $now = CarbonImmutable::now();
        $people = Teleworker::query()->where('work_center_id', $profile->id)->where('status', TeleworkerStatus::Active)->orderBy('created_at')->orderBy('id')->get();
        $names = $this->roster->findMany(array_values($people->map(fn (Teleworker $person): string => $person->employee_id)->all()));
        $nameOf = fn (Teleworker $person): string => isset($names[$person->employee_id]) ? $names[$person->employee_id]->name : '';
        $evidence = $this->evidence($tenant, $profile->id);

        return [
            'workCenterId' => $profile->id,
            'header' => [
                'legalName' => $profile->legalName,
                'rfc' => $profile->rfc,
                'workCenter' => $profile->name,
                'address' => $profile->address,
                'generatedOn' => $this->day($now),
                'generatedBy' => $generatedBy,
            ],
            'rows' => array_map(fn (ComplianceRow $row): array => [
                'numeral' => $row->numeral,
                'label' => $row->label,
                'state' => $row->state->value,
                'stateLabel' => $row->state->label(),
                'detail' => $row->detail,
            ], $this->rows->for($profile->id)),
            'roster' => $this->roster($people, $names, $personal),
            'workplaces' => $this->workplaces($people, $nameOf),
            'checklist' => $this->checklist($profile->id),
            'documents' => $this->documents($tenant, $profile->id),
            'equipment' => $this->equipment($people, $nameOf),
            'trainings' => $this->trainings($profile->id, $now),
            'overdue' => $this->overdue($people, $nameOf, $now),
            'notices' => $this->notices($profile->id),
            'committee' => $this->committee($tenant, $profile->id),
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Teleworker>  $people
     * @param  array<string, \App\Modules\Organization\Data\RosterEntry>  $names
     * @return list<array<string, mixed>>
     */
    private function roster($people, array $names, bool $personal): array
    {
        $states = $this->workCenters->states();

        return array_values($people->map(function (Teleworker $person) use ($names, $personal, $states): array {
            $entry = $names[$person->employee_id] ?? null;

            return [
                'name' => $entry === null ? '' : $entry->name,
                'jobTitle' => $entry === null ? '' : $entry->jobTitle,
                'percentage' => $person->telework_percentage,
                'startedOn' => $person->started_at === null ? null : $this->day($person->started_at),
                ...($personal ? [
                    'maritalStatus' => $person->marital_status?->value,
                    'contactPhone' => $person->contact_phone,
                    'homeAddress' => $person->homeAddress($states),
                ] : []),
            ];
        })->all());
    }

    /**
     * The workplaces in use of the active people with the latest decision of the Comisión: its date, decision and method,
     * never its notes or plan.
     *
     * @param  \Illuminate\Support\Collection<int, Teleworker>  $people
     * @param  Closure(Teleworker): string  $nameOf
     * @return list<array<string, mixed>>
     */
    private function workplaces($people, Closure $nameOf): array
    {
        $byId = $people->keyBy('id');
        $places = Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', array_values($byId->keys()->all()))->orderBy('created_at')->orderBy('id')->get();
        $reviews = $this->standing->latestReviews(array_values($places->map(fn (Workplace $place): string => $place->id)->all()));
        $months = $this->settings->periodMonths();
        $states = $this->workCenters->states();

        return array_values($places->map(function (Workplace $place) use ($byId, $nameOf, $reviews, $months): array {
            $review = $reviews[$place->id] ?? null;
            $status = $this->standing->statusOf($review, fn (): int => $months);
            $person = $byId->get($place->teleworker_id);

            return [
                'person' => $person === null ? '' : $nameOf($person),
                'status' => $status->value,
                'statusLabel' => $status->label(),
                'review' => $review === null ? null : [
                    'decidedOn' => $this->day($review->decided_at),
                    'decision' => $review->decision->label(),
                    'method' => $review->method->label(),
                    'validUntil' => $review->decision->validates() ? $this->day($this->standing->expiryAfter($review, $months)->subDay()) : null,
                ],
            ];
        })->all());
    }

    /**
     * The published checklist and the requirements of 5.1.1 and chapter 7 it leaves without a question.
     *
     * @return array<string, mixed>|null
     */
    private function checklist(string $workCenterId): ?array
    {
        $checklist = Checklist::latestPublished($workCenterId);

        if ($checklist === null) {
            return null;
        }

        $gaps = [];

        foreach ($this->coverage->missing($checklist) as $section => $requirements) {
            if ($requirements !== []) {
                $gaps[] = ['section' => $section, 'requirements' => array_map(fn ($requirement): string => $requirement->label(), $requirements)];
            }
        }

        return [
            'version' => $checklist->version,
            'publishedOn' => $checklist->published_at === null ? null : $this->day($checklist->published_at),
            'gaps' => $gaps,
        ];
    }

    /**
     * The version in force of each document with the incisos it covers and, for the ones people confirm, how many did.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(mixed $tenant, string $workCenterId): array
    {
        $documents = TeleworkDocument::query()->inForce()->where('work_center_id', $workCenterId)->orderBy('kind')->get();
        $progress = $this->acknowledgements->forDocuments(array_values($documents->filter(fn (TeleworkDocument $document): bool => $document->kind->needsAcknowledgement())->all()));

        return array_values($documents->map(fn (TeleworkDocument $document): array => [
            'numeral' => $document->kind->numeral(),
            'kind' => $document->kind->label(),
            'version' => $document->version,
            'publishedOn' => $this->day($document->created_at),
            'coveredItems' => array_map(fn (string $item): string => "{$item}) ".($document->kind->items()[$item] ?? ''), $document->covered_items),
            'missingItems' => array_values(array_map(fn (string $item): string => "{$item}) ".($document->kind->items()[$item] ?? ''), array_diff($document->kind->coverableItems(), $document->covered_items))),
            'acknowledgements' => $document->kind->needsAcknowledgement() ? ($progress[$document->id] ?? null) : null,
            'href' => route('tenant.telework.documents.file', [$tenant, $document->id]),
        ])->all());
    }

    /**
     * Per person, how many items they received, refused or have yet to confirm, and when the last was delivered.
     *
     * @param  \Illuminate\Support\Collection<int, Teleworker>  $people
     * @param  Closure(Teleworker): string  $nameOf
     * @return list<array<string, mixed>>
     */
    private function equipment($people, Closure $nameOf): array
    {
        $tally = [];

        foreach (EquipmentItem::query()->whereIn('teleworker_id', array_values($people->map(fn (Teleworker $person): string => $person->id)->all()))->get(['teleworker_id', 'status', 'delivered_on']) as $item) {
            $own = $tally[$item->teleworker_id] ?? ['received' => 0, 'refused' => 0, 'pending' => 0, 'last' => null];
            $own[$item->status->value]++;
            $day = $item->delivered_on->toDateString();
            $own['last'] = $own['last'] === null || $day > $own['last'] ? $day : $own['last'];
            $tally[$item->teleworker_id] = $own;
        }

        return array_values($people->map(fn (Teleworker $person): array => [
            'person' => $nameOf($person),
            'received' => $tally[$person->id]['received'] ?? 0,
            'refused' => $tally[$person->id]['refused'] ?? 0,
            'pending' => $tally[$person->id]['pending'] ?? 0,
            'lastDeliveredOn' => $tally[$person->id]['last'] ?? null,
            'justified' => $person->no_equipment_reason !== null,
        ])->all());
    }

    /**
     * The trainings of the last twelve months (8.1) with how many people attended.
     *
     * @return list<array<string, mixed>>
     */
    private function trainings(string $workCenterId, CarbonImmutable $now): array
    {
        $since = $now->setTimezone(config()->string('app.display_timezone'))->subMonthsNoOverflow(self::TRAINING_MONTHS)->toDateString();

        return array_values(Training::query()->where('work_center_id', $workCenterId)->where('held_on', '>=', $since)->withCount('attendees')
            ->orderByDesc('held_on')->orderBy('id')->get()
            ->map(fn (Training $training): array => [
                'heldOn' => $training->held_on->toDateString(),
                'modality' => $training->modality->label(),
                'topics' => array_map(fn ($topic): string => $topic->label(), $training->topics),
                'attendees' => $training->attendees_count,
            ])->all());
    }

    /**
     * The people whose yearly training lapsed (5.9), with the day it was due.
     *
     * @param  \Illuminate\Support\Collection<int, Teleworker>  $people
     * @param  Closure(Teleworker): string  $nameOf
     * @return list<array<string, mixed>>
     */
    private function overdue($people, Closure $nameOf, CarbonImmutable $now): array
    {
        $schedule = $this->training->schedule(array_values($people->all()));
        $today = $now->setTimezone(config()->string('app.display_timezone'))->startOfDay();
        $late = [];

        foreach ($people as $person) {
            $due = $schedule[$person->id]['due'];

            if ($this->training->standing($due, $today) === TrainingStanding::Overdue) {
                $late[] = ['person' => $nameOf($person), 'dueOn' => $due->toDateString()];
            }
        }

        return $late;
    }

    /**
     * The notices of the center's people by kind, and how many were reviewed: counts only, never what they say.
     *
     * @return array<string, mixed>
     */
    private function notices(string $workCenterId): array
    {
        $notices = WorkerNotice::query()->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->select('id'))->get(['kind', 'reviewed_at']);

        return [
            'byKind' => array_values(array_filter(array_map(
                fn (NoticeKind $kind): array => ['label' => $kind->label(), 'count' => $notices->filter(fn (WorkerNotice $notice): bool => $notice->kind === $kind)->count()],
                NoticeKind::cases(),
            ), fn (array $row): bool => $row['count'] > 0)),
            'reviewed' => $notices->filter(fn (WorkerNotice $notice): bool => $notice->reviewed_at !== null)->count(),
            'pending' => $notices->filter(fn (WorkerNotice $notice): bool => $notice->reviewed_at === null)->count(),
        ];
    }

    /**
     * The Comisión: the date of its act and the names of those who still hold a reviewing role.
     *
     * @return array<string, mixed>|null
     */
    private function committee(mixed $tenant, string $workCenterId): ?array
    {
        $committee = SafetyCommittee::query()->where('work_center_id', $workCenterId)->first();

        if ($committee === null) {
            return null;
        }

        $assigned = CommitteeAssignment::query()->where('work_center_id', $workCenterId)->pluck('user_id')->all();

        return [
            'constitutedOn' => $committee->constituted_on->toDateString(),
            'members' => array_values(array_map(
                fn (MemberSummary $member): string => $member->name,
                array_filter($this->members->reviewers(), fn (MemberSummary $member): bool => in_array($member->userId, $assigned, true)),
            )),
            'actHref' => $committee->act_path === null ? null : route('tenant.telework.committee.act', [$tenant, $workCenterId]),
        ];
    }

    /**
     * How many checklists were answered and how many photos came with them: counted, never listed, with the way back into
     * the application where the Comisión reads them.
     *
     * @return array<string, mixed>
     */
    private function evidence(mixed $tenant, string $workCenterId): array
    {
        $applications = ChecklistApplication::query()->whereNotNull('submitted_at')->whereNull('cancelled_at')
            ->whereIn('workplace_id', Workplace::query()->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->select('id'))->select('id'));

        return [
            'checklists' => (clone $applications)->count(),
            'photos' => ChecklistPhoto::query()->whereIn('application_id', (clone $applications)->select('id'))->count(),
            'href' => route('tenant.telework.reviews.index', [$tenant, $workCenterId]),
        ];
    }

    /** A calendar day in the company's display time zone. */
    private function day(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone(config()->string('app.display_timezone'))->toDateString();
    }
}
