<?php

declare(strict_types=1);

use App\Modules\Seating\Actions\ScheduleSeatingChecks;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Support\AssessmentTerm;
use App\Modules\Seating\Support\SeatingAlerts;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Seating\InternalRegulationFactory;
use Database\Factories\Seating\PauseAgreementFactory;
use Database\Factories\Seating\PositionAssignmentFactory;
use Database\Factories\Seating\RiskAssessmentFactory;
use Database\Factories\Seating\SeatFactory;
use Database\Factories\Seating\SeatReportFactory;
use Database\Factories\Seating\StandingPositionFactory;
use Illuminate\Support\Facades\Log;

mutates(ScheduleSeatingChecks::class, SeatingAlerts::class, AssessmentTerm::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
});

/** A person in the test's position (or another). */
function holder(?string $positionId = null): App\Modules\Organization\Models\Employee
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id]);
    PositionAssignmentFactory::new()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employee->id, 'position_id' => $positionId ?? test()->position->id]);

    return $employee;
}

function signedAt(string $employeeId, string $when, array $overrides = []): RiskAssessment
{
    return RiskAssessmentFactory::new()->signed()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employeeId, 'position_id' => test()->position->id, 'work_center_id' => test()->center->id, 'signed_at' => $when, ...$overrides]);
}

/** @return array<string, int> */
function alerts(): array
{
    return resolve(SeatingAlerts::class)->for(test()->center->id);
}

it('marks as due the current assessment of a person on its first anniversary, to the day', function (string $signed, bool $flagged): void {
    $person = holder();
    $assessment = signedAt($person->id, $signed);

    $count = resolve(ScheduleSeatingChecks::class)->handle();

    expect($assessment->fresh()?->reassessment_due)->toBe($flagged)->and($count)->toBe($flagged ? 1 : 0);
})->with([
    'twelve months minus a day' => ['2025-03-02 12:00:01', false],
    'twelve months plus a day' => ['2025-02-27 12:00:00', true],
    'a few weeks ago' => ['2026-02-01 12:00:00', false],
]);

it('flags only the current assessment, never the history, and keeps the signed content', function (): void {
    $person = holder();
    $old = signedAt($person->id, '2024-01-01 12:00:00');
    $current = signedAt($person->id, '2025-01-01 12:00:00');

    resolve(ScheduleSeatingChecks::class)->handle();

    expect($old->fresh()?->reassessment_due)->toBeFalse()->and($current->fresh()?->reassessment_due)->toBeTrue()
        ->and($current->fresh()?->scores)->toEqual($current->scores)->and($current->fresh()?->status->value)->toBe('signed');
});

it('does it for every company, each in its own, and logs how many', function (): void {
    Log::spy();
    $person = holder();
    $mine = signedAt($person->id, '2025-01-01 12:00:00');
    $other = Tenant::factory()->create();
    $foreign = RiskAssessmentFactory::new()->signed()->create(['tenant_id' => $other->id, 'signed_at' => '2025-01-01 12:00:00']);
    workIn($this->tenant);

    $count = resolve(ScheduleSeatingChecks::class)->handle();

    expect($count)->toBe(2)->and(RiskAssessment::query()->withoutGlobalScopes()->whereKey($foreign->id)->value('reassessment_due'))->toBeTrue()->and($mine->fresh()?->reassessment_due)->toBeTrue();
    Log::shouldHaveReceived('info')->with('seating.checks_scheduled', ['flagged' => 2])->once();
});

it('leaves alone a company that may not be used and the ones already flagged', function (): void {
    $person = holder();
    $flagged = signedAt($person->id, '2025-01-01 12:00:00', ['reassessment_due' => true]);
    $blocked = Tenant::factory()->deactivated()->create();
    $inBlocked = RiskAssessmentFactory::new()->signed()->create(['tenant_id' => $blocked->id, 'signed_at' => '2025-01-01 12:00:00']);
    workIn($this->tenant);

    $count = resolve(ScheduleSeatingChecks::class)->handle();

    expect($count)->toBe(0)->and(RiskAssessment::query()->withoutGlobalScopes()->whereKey($inBlocked->id)->value('reassessment_due'))->toBeFalse()->and($flagged->fresh()?->reassessment_due)->toBeTrue();
});

it('counts people on their feet with no signed assessment, but not those the nature of the work exempts or whose assignment ended', function (): void {
    holder();
    $exempt = StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'nature_allows' => false, 'nature_reason' => 'Prensa', 'placement' => null]);
    holder($exempt->id);
    $left = holder();
    App\Modules\Seating\Models\PositionAssignment::query()->where('employee_id', $left->id)->update(['ends_on' => '2026-02-01']);
    $withDraft = holder();
    RiskAssessmentFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $withDraft->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $assessed = holder();
    signedAt($assessed->id, '2026-02-01 12:00:00');

    expect(alerts()['unassessed'])->toBe(2);
});

it('counts the people whose assessment is due, by flag or by age, apart from those never assessed', function (): void {
    $flagged = holder();
    signedAt($flagged->id, '2026-02-01 12:00:00', ['reassessment_due' => true]);
    $old = holder();
    signedAt($old->id, '2025-02-27 12:00:00');
    $fresh = holder();
    signedAt($fresh->id, '2025-03-02 12:00:01');
    holder();

    expect(alerts())->toMatchArray(['reassessment' => 2, 'unassessed' => 1]);
});

it('alerts an open seat report only after fifteen days', function (int $days, int $count): void {
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'created_at' => now()->subDays($days)]);
    SeatReportFactory::new()->resolved()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'created_at' => now()->subDays(40)]);

    expect(alerts()['reports'])->toBe($count);
})->with([[14, 0], [15, 0], [16, 1], [60, 1]]);

it('uses the days of the configuration for the report alert', function (): void {
    config(['seating.report_alert_days' => 3]);
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'created_at' => now()->subDays(4)]);

    expect(alerts()['reports'])->toBe(1);
});

it('alerts a position whose seat goes apart from the workstation (B or C) and has no pause agreement, and not one that fits at the workstation', function (): void {
    StandingPositionFactory::new()->nearby()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'B sin acuerdo']);
    $agreed = StandingPositionFactory::new()->withinCenter()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'C con acuerdo']);
    PauseAgreementFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $agreed->id]);
    StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'A sin acuerdo']);
    StandingPositionFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Exenta', 'nature_allows' => false, 'nature_reason' => 'x', 'placement' => null]);

    expect(alerts()['pauses'])->toBe(1);
});

it('alerts a regulation that does not exist, lacks a part or has no deposit', function (string $case, int $count): void {
    match ($case) {
        'none' => null,
        'draft' => InternalRegulationFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
        'deposited' => InternalRegulationFactory::new()->distributed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'status' => 'deposited', 'distributed_on' => null]),
        'distributed' => InternalRegulationFactory::new()->distributed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]),
        'a part missing' => InternalRegulationFactory::new()->distributed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'parts' => ['risk_factors' => 'a', 'pauses' => ' ', 'seat_location' => 'c', 'usage_rule' => 'd']]),
    };

    expect(alerts()['regulation'])->toBe($count);
})->with([['none', 1], ['draft', 1], ['a part missing', 1], ['deposited', 0], ['distributed', 0]]);

it('alerts the seats away from the workstation that are not signposted', function (): void {
    SeatFactory::new()->restArea()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'signposted' => false]);
    SeatFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => null, 'signposted' => false]);
    SeatFactory::new()->restArea()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'signposted' => true]);
    SeatFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id, 'signposted' => false]);
    SeatFactory::new()->restArea()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'signposted' => false, 'condition' => 'out_of_service']);

    expect(alerts()['signage'])->toBe(2);
});

it('alerts a center with nobody on its Comisión', function (): void {
    expect(alerts()['committee'])->toBe(1);

    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);

    expect(alerts()['committee'])->toBe(0);
});

it('counts only the data of its own center and company', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    SeatReportFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id, 'created_at' => now()->subDays(60)]);
    SeatReportFactory::new()->create(['created_at' => now()->subDays(60)]);
    workIn($this->tenant);

    expect(alerts()['reports'])->toBe(0);
});
