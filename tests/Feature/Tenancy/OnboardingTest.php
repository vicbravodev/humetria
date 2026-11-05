<?php

declare(strict_types=1);

use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

// Rediseño v2 (board V3-Onboarding, docs/02 «Primera vez de una empresa»): once the company has its centers, two questions
// per center create its routes and the certificates where a standard does not apply; NOM-035's guides follow the people
// registered in each center.

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro', 'main_activity' => 'Manufactura']);
    $this->offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey', 'main_activity' => 'Administración y ventas']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('pages each center in use with its people, its NOM-035 guides and what it answered before', function (): void {
    staffOf($this->tenant, $this->plant, 30);
    staffOf($this->tenant, $this->offices, 60);
    WorkCenter::factory()->for($this->tenant)->archived()->create();
    TeleworkProfile::factory()->teleworks()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'sector' => Sector::Industrial]);

    $this->actingAs($this->hr)
        ->get(route('tenant.work-centers.onboarding', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/onboarding')
            ->where('company', 'Autopartes Demo')
            ->where('centers.total', 2)
            ->where('centers.data.0', [
                'id' => $this->offices->id, 'name' => 'Oficinas Monterrey', 'activity' => 'Administración y ventas', 'people' => 60,
                'range' => 'over_50', 'hasTeleworkers' => null, 'standing' => null, 'sector' => null,
            ])
            ->where('centers.data.1', [
                'id' => $this->plant->id, 'name' => 'Planta Querétaro', 'activity' => 'Manufactura', 'people' => 30,
                'range' => '16_to_50', 'hasTeleworkers' => true, 'standing' => true, 'sector' => 'industrial',
            ]));
});

it('keeps the answers of every center and goes to the panel, where the routes are', function (): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.work-centers.onboarding.update', $this->tenant), ['centers' => [
            ['id' => $this->plant->id, 'has_teleworkers' => true, 'standing' => true, 'sector' => 'industrial'],
            ['id' => $this->offices->id, 'has_teleworkers' => false, 'standing' => false],
        ]])
        ->assertRedirect(route('tenant.dashboard', $this->tenant))
        ->assertSessionHasNoErrors();

    expect(TeleworkProfile::query()->where('work_center_id', $this->plant->id)->sole()->has_teleworkers)->toBeTrue()
        ->and(TeleworkProfile::query()->where('work_center_id', $this->offices->id)->sole()->has_teleworkers)->toBeFalse()
        ->and(SeatingProfile::query()->where('work_center_id', $this->offices->id)->sole()->no_standing_positions)->toBeTrue();
});

it('asks both questions of every center', function (): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.work-centers.onboarding.update', $this->tenant), ['centers' => [['id' => $this->plant->id, 'standing' => false]]])
        ->assertSessionHasErrors(['centers.0.has_teleworkers' => 'El campo alguien teletrabaja es obligatorio.']);

    expect(SeatingProfile::query()->exists())->toBeFalse();
});

it('lets only whoever manages the standards set up the company', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.work-centers.onboarding', $this->tenant))->assertForbidden();
})->with([TenantRole::Viewer]);
