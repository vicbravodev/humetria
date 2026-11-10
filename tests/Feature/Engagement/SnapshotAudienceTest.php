<?php

declare(strict_types=1);

use App\Modules\Engagement\Actions\SnapshotAudience;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;

mutates(SnapshotAudience::class, SurveyClock::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->office = WorkCenter::factory()->for($this->tenant)->create();
    $this->survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @param  array<string, mixed>  $attributes
 * @return Illuminate\Database\Eloquent\Collection<int, Employee>
 */
function engagementStaff(Tenant $tenant, WorkCenter $center, Area $area, int $count, array $attributes = []): Illuminate\Database\Eloquent\Collection
{
    return Employee::factory()->count($count)->create([
        'tenant_id' => $tenant->id,
        'work_center_id' => $center->id,
        'area_id' => $area->id,
        'hired_on' => '2024-01-15',
        ...$attributes,
    ]);
}

function engagementArea(Tenant $tenant): Area
{
    return Area::factory()->create(['tenant_id' => $tenant->id]);
}

/** @return Illuminate\Support\Collection<int, Invitation> */
function invitationsOf(Survey $survey): Illuminate\Support\Collection
{
    return Invitation::query()->where('survey_id', $survey->id)->get();
}

it('invites every active person with the coarse group they are in', function (): void {
    [$packing, $dispatch, $kitchen, $sales] = [engagementArea($this->tenant), engagementArea($this->tenant), engagementArea($this->tenant), engagementArea($this->tenant)];
    engagementStaff($this->tenant, $this->plant, $packing, 5);
    $small = engagementStaff($this->tenant, $this->plant, $dispatch, 3)->merge(engagementStaff($this->tenant, $this->plant, $kitchen, 3));
    engagementStaff($this->tenant, $this->office, $sales, 5);

    $invited = resolve(SnapshotAudience::class)->handle($this->survey);

    $invitations = invitationsOf($this->survey);
    expect($invited)->toBe(16)
        ->and($invitations)->toHaveCount(16)
        ->and($invitations->where('area_id', $packing->id))->toHaveCount(5)
        ->and($invitations->where('area_id', $packing->id)->every(fn (Invitation $i): bool => $i->work_center_id === $this->plant->id && $i->tenure_band === '1to3'))->toBeTrue()
        ->and($invitations->where('area_id', $sales->id)->every(fn (Invitation $i): bool => $i->work_center_id === $this->office->id))->toBeTrue()
        ->and($invitations->whereIn('employee_id', $small->pluck('id'))->every(fn (Invitation $i): bool => $i->area_id === null && $i->tenure_band === null && $i->work_center_id === $this->plant->id))->toBeTrue()
        ->and($invitations->every(fn (Invitation $i): bool => ! $i->answered && ! $i->cancelled && $i->token_hash === null))->toBeTrue();
});

it('sends an area of three to the company when its center would not reach five without it', function (): void {
    engagementStaff($this->tenant, $this->plant, engagementArea($this->tenant), 5);
    $lonely = engagementStaff($this->tenant, $this->office, engagementArea($this->tenant), 3);

    resolve(SnapshotAudience::class)->handle($this->survey);

    $rows = invitationsOf($this->survey)->whereIn('employee_id', $lonely->pluck('id'));
    expect($rows->every(fn (Invitation $i): bool => $i->work_center_id === null && $i->area_id === null && $i->tenure_band === null && $i->manager_group_id === null))->toBeTrue();
});

it('cuts tenure by the years completed on the day of the launch', function (): void {
    $area = engagementArea($this->tenant);
    engagementStaff($this->tenant, $this->plant, $area, 5, ['hired_on' => '2025-11-10']);
    engagementStaff($this->tenant, $this->plant, $area, 5, ['hired_on' => '2025-11-09']);

    resolve(SnapshotAudience::class)->handle($this->survey);

    expect(invitationsOf($this->survey)->countBy('tenure_band')->all())->toBe(['lt1' => 5, '1to3' => 5]);
});

it('counts the recognized seniority before the hire date, and leaves whoever has none without a range', function (): void {
    $area = engagementArea($this->tenant);
    engagementStaff($this->tenant, $this->plant, $area, 5, ['hired_on' => '2026-01-01', 'seniority_on' => '2015-01-01']);
    $undated = engagementStaff($this->tenant, $this->plant, $area, 5, ['hired_on' => null]);

    resolve(SnapshotAudience::class)->handle($this->survey);

    $rows = invitationsOf($this->survey);
    expect($rows->countBy('tenure_band')->all())->toBe(['6plus' => 5, '' => 5])
        ->and($rows->whereIn('employee_id', $undated->pluck('id'))->every(fn (Invitation $i): bool => $i->tenure_band === null && $i->area_id === $area->id))->toBeTrue();
});

it('does not invite someone who was deactivated', function (): void {
    $area = engagementArea($this->tenant);
    engagementStaff($this->tenant, $this->plant, $area, 5);
    $gone = engagementStaff($this->tenant, $this->plant, $area, 1, ['deactivated_at' => now()])->sole();

    expect(resolve(SnapshotAudience::class)->handle($this->survey))->toBe(5)
        ->and(invitationsOf($this->survey)->pluck('employee_id'))->not->toContain($gone->id);
});

it('groups five direct reports under their manager and leaves the manager in no group', function (): void {
    $area = engagementArea($this->tenant);
    $boss = engagementStaff($this->tenant, $this->plant, $area, 1)->sole();
    $team = engagementStaff($this->tenant, $this->plant, $area, 5, ['manager_id' => $boss->id]);

    resolve(SnapshotAudience::class)->handle($this->survey);

    $rows = invitationsOf($this->survey);
    expect($rows->whereIn('employee_id', $team->pluck('id'))->every(fn (Invitation $i): bool => $i->manager_group_id === $boss->id && $i->about_manager_id === $boss->id && $i->comment_group_id === null))->toBeTrue()
        ->and($rows->firstWhere('employee_id', $boss->id)?->manager_group_id)->toBeNull()
        ->and($rows->firstWhere('employee_id', $boss->id)?->about_manager_id)->toBeNull();
});

it('gives the comments of a manager their own group only from ten people', function (): void {
    $area = engagementArea($this->tenant);
    $boss = engagementStaff($this->tenant, $this->plant, $area, 1)->sole();
    $team = engagementStaff($this->tenant, $this->plant, $area, 10, ['manager_id' => $boss->id]);

    resolve(SnapshotAudience::class)->handle($this->survey);

    expect(invitationsOf($this->survey)->whereIn('employee_id', $team->pluck('id'))->every(fn (Invitation $i): bool => $i->comment_group_id === $boss->id && $i->manager_group_id === $boss->id))->toBeTrue();
});

it('asks about no manager who left, and lifts the group to the next active one', function (): void {
    $area = engagementArea($this->tenant);
    $top = engagementStaff($this->tenant, $this->plant, $area, 1)->sole();
    $left = engagementStaff($this->tenant, $this->plant, $area, 1, ['manager_id' => $top->id, 'deactivated_at' => now()])->sole();
    $team = engagementStaff($this->tenant, $this->plant, $area, 5, ['manager_id' => $left->id]);

    resolve(SnapshotAudience::class)->handle($this->survey);

    $rows = invitationsOf($this->survey)->whereIn('employee_id', $team->pluck('id'));
    expect($rows)->toHaveCount(5)
        ->and($rows->every(fn (Invitation $i): bool => $i->about_manager_id === null && $i->manager_group_id === $top->id))->toBeTrue();
});

it('does not duplicate invitations when it runs twice', function (): void {
    engagementStaff($this->tenant, $this->plant, engagementArea($this->tenant), 6);
    $snapshot = resolve(SnapshotAudience::class);

    $first = $snapshot->handle($this->survey);
    $second = $snapshot->handle($this->survey);

    expect($first)->toBe(6)->and($second)->toBe(6)->and(invitationsOf($this->survey))->toHaveCount(6);
});

it('uses the day in the display time zone, not the UTC one', function (): void {
    Carbon::setTestNow('2026-11-10 03:00:00');

    expect(SurveyClock::day()->toDateString())->toBe('2026-11-09');
});
