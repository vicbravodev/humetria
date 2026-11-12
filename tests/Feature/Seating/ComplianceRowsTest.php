<?php

declare(strict_types=1);

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Models\MedicalReferral;
use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAcknowledgment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\ComplianceRow;
use App\Modules\Seating\Support\ComplianceRows;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;

mutates(ComplianceRows::class);

/**
 * A center that meets every requirement but the people's: one position with result B (so R5 and R11 apply) holding one
 * person, its seats, rest area, pause agreement, regulation, diagnosis and Comisión. `assess()` gives the person a signed,
 * confirmed assessment with its minutes. A signed assessment is final, so each variant is a new one.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    SeatingProfile::factory()->create($this->where);
    $this->position = StandingPosition::factory()->nearby()->create($this->where + ['max_people_at_once' => 2]);
    $this->person = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->person->id, 'position_id' => $this->position->id]);
    Seat::factory()->create($this->where + ['position_id' => $this->position->id, 'quantity' => 2]);
    Seat::factory()->restArea()->create($this->where + ['signposted' => true]);
    SeatingDocument::factory()->create($this->where + ['kind' => 'diagnosis_program']);
    PauseAgreement::factory()->create($this->where + ['position_id' => $this->position->id]);
    InternalRegulation::factory()->distributed()->create($this->where);
    $committee = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create($this->where + ['user_id' => $committee->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function assess(object $test, array $attributes = [], bool $confirmed = true): RiskAssessment
{
    $minutes = SeatingDocument::factory()->create($test->where);
    $assessment = RiskAssessment::factory()->signed()->create($test->where + $attributes + [
        'employee_id' => $test->person->id, 'position_id' => $test->position->id, 'recorded_in_document_id' => $minutes->id, 'signed_at' => '2026-10-02 12:00:00',
    ]);

    if ($confirmed) {
        RiskAcknowledgment::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $assessment->employee_id, 'assessment_id' => $assessment->id]);
    }

    return $assessment;
}

function row(object $test, string $key): ComplianceRow
{
    return collect(resolve(ComplianceRows::class)->for($test->center->id))->firstWhere('key', $key) ?? throw new LogicException("No row {$key}.");
}

/** @param array<string, mixed> $attributes */
function extraPerson(object $test, ?StandingPosition $position = null, array $attributes = []): Employee
{
    $person = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->center->id]);
    PositionAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $person->id, 'position_id' => ($position ?? $test->position)->id] + $attributes);

    return $person;
}

it('gives thirteen rows, r1 to r13, all met in a center that meets them', function (): void {
    assess($this);
    $rows = resolve(ComplianceRows::class)->for($this->center->id);

    expect(array_map(fn (ComplianceRow $row): string => $row->key, $rows))->toBe(array_map(fn (int $number): string => "r{$number}", range(1, 13)))
        ->and(array_map(fn (ComplianceRow $row): RowState => $row->state, $rows))->each->toBe(RowState::Met)
        ->and(array_map(fn (ComplianceRow $row): int => $row->count, $rows))->each->toBe(0)
        ->and(array_map(fn (ComplianceRow $row): bool => $row->since === null, $rows))->each->toBeTrue();
});

it('r1: asks for the sector declaration and the positions', function (): void {
    expect(row($this, 'r1')->state)->toBe(RowState::Met);

    SeatingProfile::query()->delete();
    expect(row($this, 'r1')->state)->toBe(RowState::Missing)->and(row($this, 'r1')->count)->toBe(1);
});

it('r1: is missing without positions, unless the center declares none with a date', function (): void {
    PositionAssignment::query()->delete();
    Seat::query()->delete();
    PauseAgreement::query()->delete();
    StandingPosition::query()->delete();

    expect(row($this, 'r1')->state)->toBe(RowState::Missing);

    SeatingProfile::query()->update(['no_standing_positions' => true]);
    expect(row($this, 'r1')->state)->toBe(RowState::Met);
});

it('r1: warns of a position nobody holds, and counts one with a nature that forbids sitting as documented', function (): void {
    StandingPosition::factory()->create($this->where);
    expect(row($this, 'r1')->state)->toBe(RowState::Warning)->and(row($this, 'r1')->count)->toBe(1);

    StandingPosition::query()->where('id', '!=', $this->position->id)->update(['nature_allows' => false, 'nature_reason' => 'Línea de soldadura.', 'placement' => null]);
    expect(row($this, 'r1')->state)->toBe(RowState::Met);
});

it('r2: needs a current signed assessment for each person of an eligible position', function (): void {
    expect(row($this, 'r2')->state)->toBe(RowState::Missing)->and(row($this, 'r2')->count)->toBe(1)->and(row($this, 'r2')->since?->toDateString())->toBe('2026-10-02');

    RiskAssessment::factory()->create($this->where + ['employee_id' => $this->person->id, 'position_id' => $this->position->id]);
    expect(row($this, 'r2')->state)->toBe(RowState::Missing);

    assess($this);
    expect(row($this, 'r2')->state)->toBe(RowState::Met);
});

it('r2: warns when a signed assessment is due again or a year old', function (): void {
    $assessment = assess($this);
    RiskAssessment::query()->whereKey($assessment->id)->update(['reassessment_due' => true]);
    expect(row($this, 'r2')->state)->toBe(RowState::Warning)->and(row($this, 'r2')->count)->toBe(1)->and(row($this, 'r2')->since?->toDateString())->toBe('2026-10-02');
});

it('r2: warns at a year after the signature and not before, and a newer assessment replaces the older one', function (): void {
    assess($this, ['signed_at' => '2025-11-03 12:00:00']);
    expect(row($this, 'r2')->state)->toBe(RowState::Met);

    $this->travelTo(Carbon::parse('2026-11-03 18:00:00'));
    expect(row($this, 'r2')->state)->toBe(RowState::Warning);

    assess($this, ['signed_at' => '2026-11-03 12:00:00']);
    expect(row($this, 'r2')->state)->toBe(RowState::Met);
});

it('r2: leaves out positions whose nature does not allow sitting and people no longer in a position', function (): void {
    $exempt = StandingPosition::factory()->create($this->where + ['nature_allows' => false, 'nature_reason' => 'Línea de soldadura.', 'placement' => null]);
    extraPerson($this, $exempt);
    extraPerson($this, null, ['starts_on' => '2026-01-01', 'ends_on' => '2026-06-01']);
    PositionAssignment::query()->where('position_id', $this->position->id)->where('employee_id', $this->person->id)->update(['ends_on' => '2026-10-15']);

    expect(row($this, 'r2')->state)->toBe(RowState::Met);
});

it('r3: wants the minutes of every signed assessment', function (): void {
    assess($this);
    expect(row($this, 'r3')->state)->toBe(RowState::Met);

    assess($this, ['recorded_in_document_id' => null]);

    expect(row($this, 'r3')->state)->toBe(RowState::Missing)->and(row($this, 'r3')->count)->toBe(1)->and(row($this, 'r3')->since?->toDateString())->toBe('2026-10-02');
});

it('r4: wants seats in each eligible position, warning when they are fewer than the people at once or need repair', function (): void {
    Seat::query()->where('position_id', $this->position->id)->update(['quantity' => 1]);
    expect(row($this, 'r4')->state)->toBe(RowState::Warning);

    Seat::query()->where('position_id', $this->position->id)->update(['quantity' => 2, 'condition' => SeatCondition::NeedsRepair]);
    expect(row($this, 'r4')->state)->toBe(RowState::Warning)->and(row($this, 'r4')->count)->toBe(1);

    Seat::query()->where('position_id', $this->position->id)->update(['condition' => SeatCondition::OutOfService]);
    expect(row($this, 'r4')->state)->toBe(RowState::Missing)->and(row($this, 'r4')->count)->toBe(1);

    Seat::query()->where('position_id', $this->position->id)->update(['condition' => SeatCondition::Good]);
    StandingPosition::factory()->create($this->where);
    expect(row($this, 'r4')->state)->toBe(RowState::Missing);
});

it('r4: leaves out a position whose nature does not allow sitting', function (): void {
    Seat::query()->delete();
    StandingPosition::query()->update(['nature_allows' => false, 'nature_reason' => 'Línea de soldadura.', 'placement' => null]);

    expect(row($this, 'r4')->state)->toBe(RowState::Met);
});

it('r5: wants a rest seat for the positions with result B or C only', function (): void {
    Seat::query()->where('use', 'rest_area')->delete();
    expect(row($this, 'r5')->state)->toBe(RowState::Missing)->and(row($this, 'r5')->count)->toBe(1);

    StandingPosition::factory()->create($this->where);
    expect(row($this, 'r5')->count)->toBe(1);

    StandingPosition::factory()->withinCenter()->create($this->where);
    expect(row($this, 'r5')->count)->toBe(2);

    Seat::factory()->restArea()->create($this->where + ['signposted' => true]);
    expect(row($this, 'r5')->state)->toBe(RowState::Met);
});

it('r5: does not count a rest seat out of service, and leaves out the nature that forbids sitting', function (): void {
    Seat::query()->where('use', 'rest_area')->update(['condition' => SeatCondition::OutOfService]);
    expect(row($this, 'r5')->state)->toBe(RowState::Missing);

    StandingPosition::query()->update(['nature_allows' => false, 'nature_reason' => 'Línea de soldadura.', 'placement' => null]);
    expect(row($this, 'r5')->state)->toBe(RowState::Pending);
});

it('r6: wants every seat away from the workstation signposted', function (): void {
    Seat::query()->where('use', 'rest_area')->update(['signposted' => false]);
    expect(row($this, 'r6')->state)->toBe(RowState::Missing)->and(row($this, 'r6')->count)->toBe(1);

    Seat::query()->where('use', 'rest_area')->update(['condition' => SeatCondition::OutOfService]);
    expect(row($this, 'r6')->state)->toBe(RowState::Met);

    Seat::query()->where('use', 'rest_area')->update(['condition' => SeatCondition::Good, 'signposted' => true]);
    Seat::factory()->create($this->where + ['position_id' => null]);
    expect(row($this, 'r6')->state)->toBe(RowState::Missing);
});

it('r6: does not ask a seat at the workstation itself to be signposted', function (): void {
    Seat::query()->where('position_id', $this->position->id)->update(['signposted' => false]);

    expect(row($this, 'r6')->state)->toBe(RowState::Met);
});

it('r7: wants a measure that is not cancelled for each Medium or High assessment, and warns of an overdue one', function (): void {
    $assessment = assess($this, ['level' => RiskLevel::Medium, 'total' => 10]);
    expect(row($this, 'r7')->state)->toBe(RowState::Missing)->and(row($this, 'r7')->count)->toBe(1)->and(row($this, 'r7')->since?->toDateString())->toBe('2026-10-02');

    $measure = PreventiveMeasure::factory()->create($this->where + ['assessment_id' => $assessment->id, 'position_id' => null, 'status' => MeasureStatus::Cancelled]);
    expect(row($this, 'r7')->state)->toBe(RowState::Missing);

    $measure->update(['status' => MeasureStatus::Planned, 'due_on' => '2026-12-01']);
    expect(row($this, 'r7')->state)->toBe(RowState::Met);

    $measure->update(['due_on' => '2026-11-01']);
    expect(row($this, 'r7')->state)->toBe(RowState::Warning)->and(row($this, 'r7')->count)->toBe(1)->and(row($this, 'r7')->since?->toDateString())->toBe('2026-11-01');

    $measure->update(['status' => MeasureStatus::Done]);
    expect(row($this, 'r7')->state)->toBe(RowState::Met);
});

it('r7: accepts a measure of the position as covering its people, and asks nothing of a Low assessment', function (): void {
    assess($this);
    expect(row($this, 'r7')->state)->toBe(RowState::Met);

    $high = assess($this, ['level' => RiskLevel::High, 'total' => 18, 'signed_at' => '2026-10-20 12:00:00']);
    expect(row($this, 'r7')->state)->toBe(RowState::Missing);

    PreventiveMeasure::factory()->create($this->where + ['position_id' => $this->position->id]);
    expect(row($this, 'r7')->state)->toBe(RowState::Met)->and($high->level)->toBe(RiskLevel::High);
});

it('r8: wants the confirmation of every person with a signed assessment', function (): void {
    assess($this, ['signed_at' => '2026-09-01 12:00:00']);
    expect(row($this, 'r8')->state)->toBe(RowState::Met);

    assess($this, confirmed: false);

    expect(row($this, 'r8')->state)->toBe(RowState::Missing)->and(row($this, 'r8')->count)->toBe(1)->and(row($this, 'r8')->since?->toDateString())->toBe('2026-10-02');
});

it('r9: wants a referral, or the note that none was needed, for each person with discomfort at 3', function (): void {
    assess($this, ['scores' => [...array_fill_keys(['standing_time', 'posture_change', 'surface', 'footwear', 'movement_space', 'pauses'], 1), 'discomfort' => 3]]);

    expect(row($this, 'r9')->state)->toBe(RowState::Missing)->and(row($this, 'r9')->count)->toBe(1)->and(row($this, 'r9')->since?->toDateString())->toBe('2026-10-02');

    $note = MedicalReferral::factory()->create($this->where + ['employee_id' => $this->person->id, 'referred' => false, 'note' => 'Evaluada, sin canalización.']);
    expect(row($this, 'r9')->state)->toBe(RowState::Met);

    $note->update(['referred' => true]);
    expect(row($this, 'r9')->state)->toBe(RowState::Met);
});

it('r9: asks nothing of a person whose discomfort is below 3', function (): void {
    assess($this, ['scores' => array_fill_keys(['standing_time', 'posture_change', 'surface', 'footwear', 'movement_space', 'pauses', 'discomfort'], 2)]);

    expect(row($this, 'r9')->state)->toBe(RowState::Met);
});

it('r10: goes from missing to met as the regulation is signed, deposited and distributed', function (string $stage, array $parts, RowState $expected): void {
    InternalRegulation::query()->delete();
    $regulation = InternalRegulation::factory();
    $regulation = match ($stage) {
        'draft' => $regulation,
        'signed' => $regulation->state(['status' => RegulationStatus::Signed, 'signed_on' => '2026-08-01', 'file_path' => 'regulations/rit.pdf']),
        'deposited' => $regulation->state(['status' => RegulationStatus::Deposited, 'signed_on' => '2026-08-01', 'file_path' => 'regulations/rit.pdf', 'deposited_on' => '2026-09-01', 'deposit_folio' => 'F-1']),
        'distributed' => $regulation->distributed(),
    };
    $regulation->create($this->where + ($parts === [] ? [] : ['parts' => $parts]));

    expect(row($this, 'r10')->state)->toBe($expected);
})->with(function (): array {
    $full = ['risk_factors' => 'a', 'pauses' => 'b', 'seat_location' => 'c', 'usage_rule' => 'd'];

    return [
        'draft' => ['draft', [], RowState::Missing],
        'a part empty' => ['distributed', [...$full, 'pauses' => '  '], RowState::Missing],
        'signed only' => ['signed', [], RowState::Warning],
        'deposited, not distributed' => ['deposited', [], RowState::Warning],
        'distributed' => ['distributed', [], RowState::Met],
    ];
});

it('r10: is missing without a regulation', function (): void {
    InternalRegulation::query()->delete();

    expect(row($this, 'r10')->state)->toBe(RowState::Missing)->and(row($this, 'r10')->count)->toBe(1);
});

it('r11: wants a pause agreement for each position with result B or C only', function (): void {
    PauseAgreement::query()->delete();
    expect(row($this, 'r11')->state)->toBe(RowState::Missing)->and(row($this, 'r11')->count)->toBe(1);

    StandingPosition::factory()->create($this->where);
    expect(row($this, 'r11')->count)->toBe(1);

    StandingPosition::factory()->withinCenter()->create($this->where);
    expect(row($this, 'r11')->count)->toBe(2);

    PauseAgreement::factory()->create($this->where + ['position_id' => $this->position->id]);
    expect(row($this, 'r11')->count)->toBe(1);
});

it('r12: wants the document where the analysis was put together', function (): void {
    SeatingDocument::query()->where('kind', 'diagnosis_program')->delete();
    expect(row($this, 'r12')->state)->toBe(RowState::Missing)->and(row($this, 'r12')->count)->toBe(1);

    SeatingDocument::factory()->create($this->where + ['kind' => 'other']);
    expect(row($this, 'r12')->state)->toBe(RowState::Missing);
});

it('r13: wants at least one member of the Comisión assigned to the center', function (): void {
    CommitteeAssignment::query()->delete();

    expect(row($this, 'r13')->state)->toBe(RowState::Missing)->and(row($this, 'r13')->count)->toBe(1);
});

it('counts the rows of this center only', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();

    expect(collect(resolve(ComplianceRows::class)->for($other->id))->firstWhere('key', 'r1')?->state)->toBe(RowState::Missing);
});

it('does not call met what has no assessment to look at', function (): void {
    expect(row($this, 'r2')->state)->toBe(RowState::Missing);

    foreach (['r3', 'r7', 'r8', 'r9'] as $key) {
        expect(row($this, $key)->state)->toBe(RowState::Pending)->and(row($this, $key)->count)->toBe(0);
    }
});

it('keeps them met once an assessment is signed', function (): void {
    assess($this);

    foreach (['r3', 'r7', 'r8', 'r9'] as $key) {
        expect(row($this, $key)->state)->toBe(RowState::Met);
    }
});

it('waits for a Figura 1 placement before judging measures and pauses', function (): void {
    StandingPosition::query()->update(['nature_allows' => false, 'nature_reason' => 'Línea de soldadura.', 'placement' => null]);
    expect(row($this, 'r5')->state)->toBe(RowState::Pending)->and(row($this, 'r11')->state)->toBe(RowState::Pending);

    StandingPosition::factory()->create($this->where);
    expect(row($this, 'r5')->state)->toBe(RowState::Met)->and(row($this, 'r11')->state)->toBe(RowState::Met);
});

it('waits for a seat before judging seats', function (): void {
    Seat::query()->delete();

    expect(row($this, 'r6')->state)->toBe(RowState::Pending);
});

it('labels the pending state «Sin registros»', function (): void {
    expect(RowState::Pending->label())->toBe('Sin registros');
});
