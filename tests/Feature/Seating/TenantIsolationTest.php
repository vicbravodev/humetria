<?php

declare(strict_types=1);

use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Company A can read and change nothing of company B's Ley Silla data, by query or by URL (a 404, never a 403 that would
 * tell the center exists).
 */
beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->centerB = WorkCenter::factory()->for($this->b)->create();
    $this->profileB = SeatingProfile::factory()->create(['tenant_id' => $this->b->id, 'work_center_id' => $this->centerB->id, 'sector' => 'services']);
    $this->owner = memberOf($this->a, TenantRole::Owner);
});

it('does not read the profile of another company with a query', function (): void {
    workIn($this->a);

    expect(SeatingProfile::query()->count())->toBe(0)
        ->and(SeatingProfile::query()->find($this->profileB->id))->toBeNull();

    workIn($this->b);
    expect(SeatingProfile::query()->count())->toBe(1);
});

it('answers 404 for the center of another company and never changes its profile', function (): void {
    $this->actingAs($this->owner);

    $this->get(route('tenant.seating.center', [$this->a, $this->centerB->id]))->assertNotFound();
    $this->put(route('tenant.seating.profile.update', [$this->a, $this->centerB->id]), ['sector' => 'industrial', 'no_standing_positions' => true])->assertNotFound();

    workIn($this->b);
    expect(SeatingProfile::query()->sole()->sector->value)->toBe('services');
});

it('does not list the centers of another company', function (): void {
    WorkCenter::factory()->for($this->a)->create(['name' => 'Planta A']);

    $this->actingAs($this->owner)->get(route('tenant.seating.index', $this->a))
        ->assertInertia(fn (Assert $page) => $page->where('centers.total', 1)->where('centers.data.0.name', 'Planta A'));
});
