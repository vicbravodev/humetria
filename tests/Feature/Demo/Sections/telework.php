<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Enums\WorkplaceStatus;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistAnswer;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\EquipmentItem;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\TrainingAttendee;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use App\Modules\Telework\Support\WorkplaceStanding;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Facades\Storage;

/*
 * The telework story's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'tells the telework story of the offices: twelve teleworkers with their checklists, documents, equipment, training and the Comisión' => function (array $logged): void {
        $tenantId = resolve(DemoCompanies::class)->id();
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $offices = (string) collect(resolve(WorkCenters::class)->all())->firstWhere('name', DemoRoster::CENTERS['offices'])?->id;
        $hr = User::query()->where('email', DemoRoster::person(DemoRoster::HR)->email)->sole();
        $people = collect(DemoRoster::people())->filter(fn (DemoPerson $person): bool => $person->centerKey === 'offices')
            ->keyBy(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key));
        $door = $people->filter(fn (DemoPerson $person): bool => $person->reservedForDoor);
        $standing = resolve(WorkplaceStanding::class);

        // the offices declare telework, have their Comisión (HR on it) for over a year and its published checklist
        $profile = TeleworkProfile::query()->sole();
        $committee = SafetyCommittee::query()->sole();
        $checklist = Checklist::query()->where('status', ChecklistStatus::Published)->sole();
        expect($profile->work_center_id)->toBe($offices)
            ->and($profile->has_teleworkers)->toBeTrue()
            ->and($committee->work_center_id)->toBe($offices)
            ->and($committee->constituted_on->lessThan($today->subYear()))->toBeTrue()
            ->and(resolve(Committee::class)->isMember($hr->id, $offices))->toBeTrue()
            ->and($checklist->work_center_id)->toBe($offices)
            ->and($checklist->version)->toBe(1)
            ->and(Checklist::query()->count())->toBe(1);

        // twelve teleworkers of the offices, none kept for the door: ten in the roster with their 5.1 data, two aspirants
        $teleworkers = Teleworker::query()->get();
        $active = $teleworkers->where('status', TeleworkerStatus::Active);
        $aspirants = $teleworkers->where('status', TeleworkerStatus::Aspirant);
        expect($teleworkers)->toHaveCount(12)
            ->and($teleworkers->every(fn (Teleworker $teleworker): bool => $teleworker->work_center_id === $offices && isset($people[$teleworker->employee_id]) && ! $people[$teleworker->employee_id]->reservedForDoor))->toBeTrue()
            ->and($teleworkers->every(fn (Teleworker $teleworker): bool => $teleworker->telework_percentage > 40 && $teleworker->telework_percentage <= 100))->toBeTrue()
            ->and($active)->toHaveCount(10)
            ->and($aspirants)->toHaveCount(2)
            ->and($active->every(fn (Teleworker $teleworker): bool => $teleworker->started_at?->lessThan($today) === true && $teleworker->marital_status !== null && $teleworker->contact_phone !== null && $teleworker->street !== null))->toBeTrue()
            // nobody teleworks before they joined the company
            ->and($teleworkers->every(fn (Teleworker $teleworker): bool => $teleworker->created_at?->greaterThan($today->subDays($people[$teleworker->employee_id]->tenureDays)) === true))->toBeTrue();

        // one workplace each; every one in the roster validated at least once, one of them with an action plan
        $workplaces = Workplace::query()->get()->keyBy('teleworker_id');
        $reviews = WorkplaceReview::query()->get();
        expect($workplaces)->toHaveCount(12);
        foreach ($active as $teleworker) {
            expect($reviews->where('workplace_id', $workplaces[$teleworker->id]->id)->filter(fn (WorkplaceReview $review): bool => $review->decision->validates()))->not->toBeEmpty();
        }
        expect($reviews->where('decision', ReviewDecision::ValidWithPlan))->not->toBeEmpty()
            ->and($reviews->every(fn (WorkplaceReview $review): bool => $review->decided_by === $hr->id && $review->decided_at->lessThan($today)))->toBeTrue();

        // their checklists: every Sección I sent but one aspirant's, still pending; the other aspirant's waits for the Comisión,
        // which asked for photos; the Sección II of the year-old ones was asked and answered, but one is still waiting and its
        // workplace lapsed
        $applications = ChecklistApplication::query()->get();
        $workplaceOf = $workplaces->mapWithKeys(fn (Workplace $workplace): array => [$workplace->id => $workplace->teleworker_id]);
        $first = $applications->where('section', Section::One);
        $second = $applications->where('section', Section::Two);
        expect($first)->toHaveCount(12)
            ->and($first->whereNull('submitted_at'))->toHaveCount(1)
            ->and($aspirants->pluck('id')->all())->toContain($workplaceOf[$first->whereNull('submitted_at')->sole()->workplace_id]);
        $waiting = $aspirants->first(fn (Teleworker $teleworker): bool => $first->where('workplace_id', $workplaces[$teleworker->id]->id)->whereNotNull('submitted_at')->isNotEmpty());
        expect($waiting)->not->toBeNull()
            ->and($reviews->where('workplace_id', $workplaces[$waiting?->id]->id))->toBeEmpty()
            ->and(EvidenceRequest::query()->where('workplace_id', $workplaces[$waiting?->id]->id)->where('kind', EvidenceKind::Photos)->whereNull('fulfilled_at')->count())->toBe(1)
            ->and($second)->not->toBeEmpty()
            ->and($second->whereNull('submitted_at'))->toHaveCount(1);
        $lapsed = $workplaces[$workplaceOf[$second->whereNull('submitted_at')->sole()->workplace_id]];
        expect($standing->of($lapsed))->toBe(WorkplaceStatus::Pending)
            ->and($active->filter(fn (Teleworker $teleworker): bool => $standing->of($workplaces[$teleworker->id]) === WorkplaceStatus::Pending)->pluck('id')->all())->toBe([$lapsed->teleworker_id])
            ->and($applications->whereNotNull('submitted_at')->every(fn (ChecklistApplication $application): bool => $application->submitted_at->lessThan($today) && $application->consent_accepted_at !== null))->toBeTrue();
        // whoever found a risk described it
        $notes = ChecklistAnswer::query()->whereNotNull('risk_note')->pluck('risk_note');
        expect($notes)->not->toBeEmpty();

        // the policy and the risk information are published in the offices as PDFs; everyone confirmed the policy but the
        // door, and the roster and the Comisión the risk information
        $policy = TeleworkDocument::query()->where('kind', DocumentKind::Policy)->sole();
        $risk = TeleworkDocument::query()->where('kind', DocumentKind::RiskInformation)->sole();
        expect($policy->work_center_id)->toBe($offices)
            ->and($risk->work_center_id)->toBe($offices)
            ->and($policy->covered_items)->toBe(array_keys(DocumentKind::Policy->items()))
            ->and(str_starts_with((string) Storage::disk(config()->string('filesystems.telework_files'))->get((string) $policy->path), '%PDF-'))->toBeTrue();
        $policyAcks = Acknowledgement::query()->where('document_id', $policy->id)->get();
        expect($policyAcks->pluck('employee_id')->sort()->values()->all())->toBe($people->keys()->sort()->values()->all())
            ->and($policyAcks->whereNull('acknowledged_at')->pluck('employee_id')->sort()->values()->all())->toBe($door->keys()->sort()->values()->all())
            ->and($policyAcks->whereNotNull('acknowledged_at')->every(fn (Acknowledgement $ack): bool => $ack->acknowledged_at->lessThan($today)
                && $ack->acknowledged_at->greaterThan($today->subDays($people[$ack->employee_id]->tenureDays))))->toBeTrue();
        $riskAcks = Acknowledgement::query()->where('document_id', $risk->id)->get();
        expect($riskAcks->whereNotNull('employee_id')->pluck('employee_id')->sort()->values()->all())->toBe($active->pluck('employee_id')->sort()->values()->all())
            ->and($riskAcks->whereNotNull('user_id')->pluck('user_id')->all())->toBe([$hr->id])
            ->and($riskAcks->every(fn (Acknowledgement $ack): bool => $ack->acknowledged_at !== null))->toBeTrue();

        // the equipment of everyone in the roster, received but one item that waits for its person
        $equipment = EquipmentItem::query()->get();
        foreach ($active as $teleworker) {
            expect($equipment->where('teleworker_id', $teleworker->id))->not->toBeEmpty();
        }
        expect($equipment->where('status', EquipmentStatus::Pending))->toHaveCount(1)
            ->and($equipment->where('status', EquipmentStatus::Received)->count())->toBe($equipment->count() - 1);

        // trainings of the roster, the latest with everyone in it
        $trainings = Training::query()->orderBy('held_on')->get();
        expect($trainings)->not->toBeEmpty()
            ->and($trainings->every(fn (Training $training): bool => $training->work_center_id === $offices && $training->held_on->lessThan($today)))->toBeTrue()
            ->and(TrainingAttendee::query()->where('training_id', $trainings->last()?->id)->pluck('employee_id')->sort()->values()->all())->toBe($active->pluck('employee_id')->sort()->values()->all());

        // the portal works: the aspirant opens the list that waits for them, a person in the roster confirms the item that
        // waits, and a visitor of the door confirms the policy
        $pending = $first->whereNull('submitted_at')->sole();
        $token = resolve(PortalAccess::class)->token($teleworkers->firstWhere('id', $workplaceOf[$pending->workplace_id])?->employee_id ?? '');
        $this->get(route('portal.show', $token))->assertOk();
        $this->get(route('portal.checklist.show', [$token, $pending->id]))->assertOk();
        $item = $equipment->firstWhere('status', EquipmentStatus::Pending);
        $token = resolve(PortalAccess::class)->token($teleworkers->firstWhere('id', $item?->teleworker_id)?->employee_id ?? '');
        $this->post(route('portal.equipment.respond', [$token, $item?->id]), ['received' => true])->assertRedirect();
        expect($item?->refresh()->status)->toBe(EquipmentStatus::Received);
        $visitor = $door->keys()->first();
        $token = resolve(PortalAccess::class)->token($visitor);
        $this->get(route('portal.show', $token))->assertOk();
        $this->post(route('portal.documents.acknowledge', [$token, $policy->id]))->assertRedirect();
        expect(Acknowledgement::query()->where('document_id', $policy->id)->where('employee_id', $visitor)->sole()->acknowledged_at)->not->toBeNull();

        // no risk note, home address or phone reaches the logs or the audit
        $audits = AuditEntry::query()->where('tenant_id', $tenantId)->get()->map(fn (AuditEntry $entry): string => $entry->event.' '.json_encode($entry->metadata))->implode("\n");
        $logs = implode("\n", $logged);
        $private = $notes->merge($teleworkers->pluck('street'))->merge($teleworkers->pluck('contact_phone'))->merge($reviews->pluck('action_plan'))->filter()->unique();
        foreach ($private as $text) {
            expect(str_contains($logs, (string) $text))->toBeFalse()->and(str_contains($audits, (string) $text))->toBeFalse();
        }

        // nothing outside the demo company
        foreach ([TeleworkProfile::class, SafetyCommittee::class, Checklist::class, Teleworker::class, Workplace::class, ChecklistApplication::class, ChecklistAnswer::class, WorkplaceReview::class, EvidenceRequest::class, TeleworkDocument::class, Acknowledgement::class, EquipmentItem::class, Training::class, TrainingAttendee::class] as $model) {
            expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
        }
    },
];
