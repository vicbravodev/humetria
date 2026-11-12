<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\CenterBoard;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CenterBoard::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id];
    $this->url = route('tenant.seating.center', [$this->tenant, $this->center->id]);
});

/** A position with `$people` people in it and a signed assessment for each, so every list has something. */
function fillCenter(object $test, int $positions, int $people): void
{
    SeatingProfile::query()->exists() || SeatingProfile::factory()->create($test->where);

    for ($index = 0; $index < $positions; $index++) {
        $position = StandingPosition::factory()->create($test->where);
        Seat::factory()->create($test->where + ['position_id' => $position->id, 'quantity' => 2]);

        for ($person = 0; $person < $people; $person++) {
            $employee = Employee::factory()->create(['tenant_id' => $test->tenant->id, 'work_center_id' => $test->center->id]);
            PositionAssignment::factory()->create(['tenant_id' => $test->tenant->id, 'employee_id' => $employee->id, 'position_id' => $position->id]);
            RiskAssessment::factory()->signed()->create($test->where + ['employee_id' => $employee->id, 'position_id' => $position->id, 'signed_at' => '2026-10-02 12:00:00']);
        }
    }
}

it('gives the thirteen requirements with their state, count and date, and a summary', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->has('requirements', 13)
        ->where('requirements.0', ['key' => 'r1', 'number' => 'R1', 'label' => 'Sector declarado y puestos con su Figura 1', 'tab' => 'positions', 'state' => 'missing', 'stateLabel' => 'Falta', 'count' => 1, 'since' => null])
        ->where('requirements', fn ($rows) => collect($rows)->pluck('key')->all() === array_map(fn (int $number): string => "r{$number}", range(1, 13)))
        ->where('summary', ['met' => 2, 'warning' => 0, 'missing' => 4, 'pending' => 7]));
});

it('carries the date a requirement has been waiting since, written as a day', function (): void {
    fillCenter($this, 1, 1);
    RiskAssessment::query()->update(['reassessment_due' => true]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->where('requirements.1.key', 'r2')
        ->where('requirements.1.state', 'warning')
        ->where('requirements.1.count', 1)
        ->where('requirements.1.since', '2026-10-02'));
});

it('shows each role the tabs it may open, with how many each holds', function (TenantRole $role, array $tabs): void {
    fillCenter($this, 2, 2);
    PreventiveMeasure::factory()->create($this->where + ['position_id' => StandingPosition::query()->firstOrFail()->id]);
    SeatingDocument::factory()->count(3)->create($this->where);
    SeatReport::factory()->create($this->where);
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create($this->where + ['user_id' => $user->id]);

    $this->actingAs($user)->get($this->url)->assertInertia(fn (Assert $page) => $page
        ->where('tabs', fn ($found) => collect($found)->map(fn ($tab) => [$tab['key'], $tab['count']])->all() === $tabs));
})->with([
    'owner' => [TenantRole::Owner, [['positions', 2], ['people', 4], ['inventory', 2], ['reports', 1], ['plan', 1], ['regulation', null], ['documents', 3], ['survey', null]]],
    'hr' => [TenantRole::Hr, [['positions', 2], ['people', 4], ['inventory', 2], ['reports', 1], ['plan', 1], ['regulation', null], ['documents', 3], ['survey', null]]],
    'committee' => [TenantRole::Committee, [['positions', 2], ['people', 4], ['inventory', 2], ['reports', 1], ['plan', 1], ['regulation', null], ['documents', 3]]],
    'viewer' => [TenantRole::Viewer, [['positions', 2], ['inventory', 2]]],
]);

it('shows the viewer counts only, never a name', function (): void {
    fillCenter($this, 1, 2);
    $name = Employee::query()->first()->name ?? throw new LogicException('Someone is needed.');

    $response = $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get($this->url)->assertOk();

    expect((string) json_encode($response->viewData('page')['props']))->not->toContain($name);
});

it('asserts the query count of the center page', function (): void {
    fillCenter($this, 2, 3);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $this->get($this->url)->assertOk();
    DB::enableQueryLog();
    $this->get($this->url)->assertOk();
    $small = count(DB::getQueryLog());
    DB::flushQueryLog();

    fillCenter($this, 6, 6);
    DB::flushQueryLog();
    $this->get($this->url)->assertOk();

    // More positions, people and seats, the same queries: no query depends on how many there are.
    expect(count(DB::getQueryLog()))->toBe($small);

    $this->expectsDatabaseQueryCount($small);
    $this->get($this->url)->assertOk();
});
