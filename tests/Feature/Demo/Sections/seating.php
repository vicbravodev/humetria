<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Contracts\AnswerDistributions;
use App\Modules\Seating\Enums\AssessmentStatus;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Contracts\Committee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The seating story's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'leaves on purpose, for the presenter to complete live, the items the manual names as missing' => function (): void {
        $offices = collect(resolve(WorkCenters::class)->all())->firstWhere('name', DemoRoster::CENTERS['offices'])?->id ?? '';
        $rows = resolve(App\Modules\Seating\Support\CenterBoard::class)->for($offices)['requirements'];
        $missing = array_values(array_map(fn (array $row): string => $row['key'], array_filter($rows, fn (array $row): bool => $row['state'] === 'missing')));

        // docs/operations/demo.md, section 4
        expect($missing)->toBe(['r2', 'r3', 'r8', 'r10', 'r12'], 'states: '.json_encode(array_column($rows, 'state', 'key')));
    },

    'tells the Ley Silla story of the offices: positions, seats, signed assessments, open and closed measures and a keyless survey' => function (array &$logged): void {
        $tenantId = resolve(DemoCompanies::class)->id();
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        $offices = collect(resolve(WorkCenters::class)->all())->firstWhere('name', DemoRoster::CENTERS['offices'])?->id;
        $hr = App\Models\User::query()->where('email', DemoRoster::person(DemoRoster::HR)->email)->sole();
        $people = collect(DemoRoster::people())->filter(fn (DemoPerson $person): bool => $person->centerKey === 'offices')
            ->keyBy(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key));
        // Who works standing in the offices: the counter of the sales team, the on-site support and the filing and messenger runs.
        $standing = $people->filter(fn (DemoPerson $person): bool => in_array($person->position, ['Asistente comercial', 'Soporte técnico', 'Auxiliar contable'], true));

        // the offices declare a services center that has people working standing
        $profile = SeatingProfile::query()->sole();
        expect($profile->work_center_id)->toBe($offices)
            ->and($profile->sector)->toBe(Sector::Services)
            ->and($profile->no_standing_positions)->toBeFalse()
            ->and($profile->declared_on->lessThan($today))->toBeTrue();

        // three standing positions with their Figura 1, the people of the roster in them, one position each
        $positions = StandingPosition::query()->get();
        $assignments = PositionAssignment::query()->whereNull('ends_on')->get();
        expect($positions)->toHaveCount(3)
            ->and($positions->every(fn (StandingPosition $position): bool => $position->work_center_id === $offices && $position->placement !== null))->toBeTrue()
            ->and($positions->pluck('placement')->unique()->count())->toBeGreaterThanOrEqual(2)
            ->and($assignments->pluck('employee_id')->sort()->values()->all())->toBe($standing->keys()->sort()->values()->all())
            ->and($assignments->every(fn (PositionAssignment $assignment): bool => $assignment->starts_on->lessThan($today)))->toBeTrue();
        foreach ($positions as $position) {
            expect($assignments->where('position_id', $position->id))->not->toBeEmpty();
        }

        // seats in every position, and a designated rest area for the positions whose seat is not at the workstation
        $seats = Seat::query()->get();
        foreach ($positions as $position) {
            expect($seats->where('position_id', $position->id)->where('use', SeatUse::Workstation)->sum('quantity'))->toBeGreaterThanOrEqual($position->max_people_at_once);
        }
        $rest = $seats->where('use', SeatUse::RestArea);
        expect($rest)->not->toBeEmpty()
            ->and($rest->every(fn (Seat $seat): bool => trim((string) $seat->location) !== '' && $seat->signposted))->toBeTrue()
            ->and($positions->contains(fn (StandingPosition $position): bool => $position->placement !== SeatPlacement::AtWorkstation))->toBeTrue();

        // the Comisión of the offices (HR sits on it) signed the assessment of every standing person but one, still a draft
        $assessments = RiskAssessment::query()->get();
        $signed = $assessments->where('status', AssessmentStatus::Signed);
        $drafts = $assessments->where('status', AssessmentStatus::Draft);
        expect(resolve(Committee::class)->isMember($hr->id, (string) $offices))->toBeTrue()
            ->and($assessments->pluck('employee_id')->unique()->sort()->values()->all())->toBe($standing->keys()->sort()->values()->all())
            ->and($drafts)->toHaveCount(1)
            ->and($signed)->toHaveCount($standing->count() - 1)
            ->and($signed->every(fn (RiskAssessment $assessment): bool => $assessment->signed_by === $hr->id && $assessment->signed_at?->lessThan($today) === true && $assessment->total !== null))->toBeTrue()
            ->and($signed->pluck('level')->map(fn (RiskLevel $level): string => $level->value)->unique()->sort()->values()->all())->toBe(['high', 'low', 'medium']);

        // measures both closed (done) and open (planned or in progress), every Medio or Alto assessment with one that stands
        $measures = PreventiveMeasure::query()->get();
        expect($measures->where('status', MeasureStatus::Done))->not->toBeEmpty()
            ->and($measures->whereIn('status', [MeasureStatus::Planned, MeasureStatus::InProgress]))->not->toBeEmpty()
            ->and($measures->every(fn (PreventiveMeasure $measure): bool => $measure->work_center_id === $offices && $measure->owner_user_id === $hr->id && $measure->due_on !== null))->toBeTrue();
        foreach ($signed->filter(fn (RiskAssessment $assessment): bool => $assessment->level !== RiskLevel::Low) as $assessment) {
            expect($measures->where('assessment_id', $assessment->id)->where('status', '!=', MeasureStatus::Cancelled))->not->toBeEmpty();
        }

        // a pause agreement for each position whose seat is not at the workstation
        $agreements = PauseAgreement::query()->get();
        foreach ($positions->filter(fn (StandingPosition $position): bool => $position->placement !== SeatPlacement::AtWorkstation) as $position) {
            expect($agreements->where('position_id', $position->id))->toHaveCount(1);
        }

        // everyone with a signed assessment confirmed it from their portal, except the people kept for the door
        $acknowledged = RiskAcknowledgment::query()->get();
        $expected = $signed->reject(fn (RiskAssessment $assessment): bool => $people[$assessment->employee_id]->reservedForDoor);
        expect($acknowledged->pluck('assessment_id')->sort()->values()->all())->toBe($expected->pluck('id')->sort()->values()->all())
            ->and($acknowledged->every(fn (RiskAcknowledgment $ack): bool => $ack->acknowledged_at->lessThan($today)))->toBeTrue();
        // a visitor of the door with a standing position confirms it live
        $visitor = $signed->first(fn (RiskAssessment $assessment): bool => $people[$assessment->employee_id]->reservedForDoor);
        expect($visitor)->not->toBeNull();
        $token = resolve(PortalAccess::class)->token((string) $visitor?->employee_id);
        $this->get(route('portal.seating.show', $token))->assertOk();
        $this->post(route('portal.seating.acknowledgments.store', $token))->assertRedirect(route('portal.seating.show', $token));
        expect(RiskAcknowledgment::query()->where('assessment_id', $visitor?->id)->exists())->toBeTrue();

        // the confidential survey went to the 30 people of the offices, everyone but the door answered, and it closed
        $survey = Campaign::query()->where('kind', CampaignKind::SeatingSurvey)->sole();
        $participants = Participant::query()->where('campaign_id', $survey->id)->get();
        $done = ParticipantInstrument::query()->whereIn('participant_id', $participants->pluck('id'))->get()->keyBy('participant_id');
        expect($survey->work_center_id)->toBe($offices)
            ->and($survey->status)->toBe(CampaignStatus::Closed)
            ->and($survey->closed_at?->lessThan($today))->toBeTrue()
            ->and($participants->pluck('employee_id')->sort()->values()->all())->toBe($people->keys()->sort()->values()->all());
        foreach ($participants as $participant) {
            expect($done[$participant->id]->status)->toBe($people[$participant->employee_id]->reservedForDoor ? InstrumentStatus::Pending : InstrumentStatus::Completed);
        }

        // its answers are stored with no key to anyone, and shown only for groups of five or more
        $responses = AnonymousResponse::query()->where('campaign_id', $survey->id)->get();
        $references = collect(DB::select(<<<'SQL'
            SELECT DISTINCT target.relname AS referenced FROM pg_constraint c
            JOIN pg_class source ON source.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid
            WHERE source.relname = 'anonymous_responses' AND c.contype = 'f' ORDER BY referenced
        SQL))->pluck('referenced')->all();
        $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'anonymous_responses'"))->pluck('column_name')->all();
        expect($responses)->toHaveCount($people->reject(fn (DemoPerson $person): bool => $person->reservedForDoor)->count())
            ->and($references)->toBe(['areas', 'campaigns', 'tenants', 'work_centers'])
            ->and(array_intersect($columns, ['employee_id', 'participant_id', 'invitation_id', 'token_hash']))->toBe([])
            ->and($responses->pluck('id')->every(fn (string $id): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) === 1))->toBeTrue();
        $results = resolve(AnswerDistributions::class)->of($survey->id);
        expect($results->visible)->toBeTrue()
            ->and($results->answered)->toBe($responses->count())
            ->and($results->hiddenAreas)->toBeGreaterThanOrEqual(1)
            ->and(collect($results->areas)->every(fn ($area): bool => $area->answered >= 5))->toBeTrue()
            // the standing people answered the questions: some say the pauses are not kept, nobody was kept from sitting
            ->and(collect($results->questions)->every(fn ($question): bool => $question->visible))->toBeTrue()
            ->and(collect($results->questions)->firstWhere('number', 3)?->yes)->toBe(0)
            ->and(collect($results->questions)->firstWhere('number', 5)?->no)->toBeGreaterThan(0);

        // nothing of an answer, a score or a note reaches the logs or the audit
        $audits = AuditEntry::query()->where('tenant_id', $tenantId)->get()->map(fn (AuditEntry $entry): string => $entry->event.' '.json_encode($entry->metadata))->implode("\n");
        $logs = implode("\n", $logged);
        foreach ($responses->pluck('id') as $id) {
            expect(str_contains($logs, $id))->toBeFalse()->and(str_contains($audits, $id))->toBeFalse();
        }
        $notes = $signed->pluck('other_aspects')->filter()->values();
        expect($notes)->not->toBeEmpty();
        foreach ($notes as $note) {
            expect(str_contains($logs, (string) $note))->toBeFalse()->and(str_contains($audits, (string) $note))->toBeFalse();
        }
        expect(str_contains($logs, '"scores"'))->toBeFalse()->and(str_contains($audits, '"scores"'))->toBeFalse();

        // preparing the demo writes its NOM-037 PDFs to the fake disk every demo test gets, never to the real one
        $disk = Storage::disk(config()->string('filesystems.telework_files'));
        expect($disk->path(''))->toContain('framework/testing/disks')
            ->and($disk->allFiles("documents/{$tenantId}"))->toHaveCount(2);

        // nothing outside the demo company
        foreach ([SeatingProfile::class, StandingPosition::class, PositionAssignment::class, Seat::class, RiskAssessment::class, PreventiveMeasure::class, PauseAgreement::class, RiskAcknowledgment::class] as $model) {
            expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
        }
    },
];
