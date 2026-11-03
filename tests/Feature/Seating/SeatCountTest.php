<?php

declare(strict_types=1);

use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SeatCount;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SeatCount::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera', 'max_people_at_once' => 3]);
});

function seatsFor(StandingPosition $position, int $quantity, string $condition = 'good'): void
{
    Seat::factory()->create(['tenant_id' => $position->tenant_id, 'work_center_id' => $position->work_center_id, 'position_id' => $position->id, 'quantity' => $quantity, 'condition' => $condition]);
}

it('flags a shortfall when the seats are fewer than the people at once', function (int $seats, bool $short): void {
    if ($seats > 0) {
        seatsFor($this->position, $seats);
    }

    $result = resolve(SeatCount::class)->forPosition($this->position->id);

    expect($result->seats)->toBe($seats)->and($result->maxPeopleAtOnce)->toBe(3)->and($result->short)->toBe($short);
})->with([
    'none' => [0, true],
    'one fewer' => [2, true],
    'exactly as many' => [3, false],
    'more' => [5, false],
]);

it('adds the quantity of every seat of the position and leaves out those out of service', function (): void {
    seatsFor($this->position, 1);
    seatsFor($this->position, 1, 'needs_repair');
    seatsFor($this->position, 4, 'out_of_service');
    Seat::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => null, 'quantity' => 9]);

    expect(resolve(SeatCount::class)->forPosition($this->position->id)->seats)->toBe(2);
});

it('counts many positions at once without mixing them', function (): void {
    $other = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'max_people_at_once' => 1]);
    seatsFor($this->position, 3);
    seatsFor($other, 1);

    $results = resolve(SeatCount::class)->forPositions([$this->position->id, $other->id]);

    expect($results[$this->position->id]->short)->toBeFalse()->and($results[$other->id]->seats)->toBe(1);
});

it('shows the shortfall on the seats page and says it is an aid, not a standard', function (): void {
    seatsFor($this->position, 1);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.seating.seats.index', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('shortfalls.0', ['positionId' => $this->position->id, 'name' => 'Cajera', 'seats' => 1, 'maxPeopleAtOnce' => 3])
            ->where('shortfallNote', fn (string $note) => str_contains($note, 'no fija')));
});
