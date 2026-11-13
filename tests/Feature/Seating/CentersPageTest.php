<?php

declare(strict_types=1);

use App\Modules\Seating\Http\Controllers\SeatingController;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SeatingPages;
use App\Modules\Seating\Support\VisibleCenters;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SeatingController::class, SeatingPages::class, VisibleCenters::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte', 'main_activity' => 'Manufactura']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $this->cora->id]);
});

it('lists every center in use for owner, hr and viewer, and only its own for the committee', function (TenantRole $role, array $names): void {
    $this->actingAs(memberOf($this->tenant, $role));
    WorkCenter::factory()->for($this->tenant)->archived()->create(['name' => 'Planta Vieja']);

    $this->get(route('tenant.seating.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/index')
            ->where('centers.total', count($names))
            ->where('centers.data', fn ($centers) => collect($centers)->pluck('name')->sort()->values()->all() === $names));
})->with([
    'owner' => [TenantRole::Owner, ['Planta Norte', 'Planta Sur']],
    'hr' => [TenantRole::Hr, ['Planta Norte', 'Planta Sur']],
    'viewer' => [TenantRole::Viewer, ['Planta Norte', 'Planta Sur']],
]);

it('lists only the centers the committee sits on', function (): void {
    $this->actingAs($this->cora)->get(route('tenant.seating.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('centers.total', 1)
            ->where('centers.data.0.id', $this->north->id)
            ->where('centers.data.0.name', 'Planta Norte'));
});

// Rediseño v2 (the pattern of board V2-Centros-N037): each center with the people who work standing there, where it stands in
// its route and what comes next; above, how many people in how many centers.
it('gives each center its people and where it stands in its route, and the people of every center it sees', function (): void {
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'sector' => 'commerce']);
    $position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);
    PositionAssignment::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.seating.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary', ['people' => 2, 'centers' => 1])
            ->where('canManage', true)
            ->where('centers.data', fn ($centers): bool => collect($centers)->firstWhere('id', $this->north->id)['people'] === 2
                && collect($centers)->firstWhere('id', $this->north->id)['route'] === ['applies' => true, 'done' => [true, false, false, false, false, false], 'status' => 'pon asientos donde hacen falta', 'answered' => true]
                && collect($centers)->firstWhere('id', $this->south->id)['people'] === 0
                && collect($centers)->firstWhere('id', $this->south->id)['route']['answered'] === false));
});

it('reads the list in a fixed number of queries, however many centers it shows', function (): void {
    WorkCenter::factory()->for($this->tenant)->count(4)->create();
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->expectsDatabaseQueryCount(QUERIES_OF_THE_SEATING_CENTERS);
    $this->get(route('tenant.seating.index', $this->tenant))->assertOk();
});

// Measured, then fixed so that a query per center breaks the test.
const QUERIES_OF_THE_SEATING_CENTERS = 31;

it('paginates the centers on the server', function (): void {
    WorkCenter::factory()->for($this->tenant)->count(12)->create();
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));

    $this->get(route('tenant.seating.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.total', 14)->where('centers.per_page', 12)->has('centers.data', 12));
    $this->get(route('tenant.seating.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('centers.data', 2));
});

it('shows the viewer the list without any personal data', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.seating.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('centers.data.0', fn ($card) => array_keys($card->toArray()) === ['id', 'name', 'activity', 'people', 'route']));
});

it('opens a center with its declaration and what the person may do', function (TenantRole $role, bool $manage): void {
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'sector' => 'services', 'note' => 'Oficinas']);
    $user = $role === TenantRole::Committee ? $this->cora : memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.seating.center', [$this->tenant, $this->north->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/center')
            ->where('workCenter', ['id' => $this->north->id, 'name' => 'Planta Norte', 'activity' => 'Manufactura'])
            ->where('profile.sector', 'services')
            ->where('profile.note', 'Oficinas')
            ->where('canManage', $manage)
            ->has('sectors', 4));
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('answers 404 for an archived center, a made-up one, a malformed id and a center the committee does not sit on', function (): void {
    $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();

    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    $this->get(route('tenant.seating.center', [$this->tenant, $archived->id]))->assertNotFound();
    $this->get(route('tenant.seating.center', [$this->tenant, (string) Str::uuid()]))->assertNotFound();
    $this->get(route('tenant.seating.center', [$this->tenant, 'nope']))->assertNotFound();
    $this->actingAs($this->cora)->get(route('tenant.seating.center', [$this->tenant, $this->south->id]))->assertNotFound();
});

it('shows an undeclared center with an empty profile', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.seating.center', [$this->tenant, $this->south->id]))
        ->assertInertia(fn (Assert $page) => $page->where('profile', null));
});
