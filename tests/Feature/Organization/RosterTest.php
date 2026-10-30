<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyRoster;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyRoster::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id] + ['name' => 'Producción']);
});

function rosterEmployee(Tenant $tenant, WorkCenter $workCenter, Area $area, array $attributes = []): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $workCenter->id, 'area_id' => $area->id, ...$attributes]);
}

it('lists the active people of a work center by name, with their area and gender', function (): void {
    $zoe = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Zoé Ruiz', 'gender' => Gender::Female, 'email' => 'zoe@acme.mx']);
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'gender' => Gender::Other, 'email' => null, 'mobile' => '5512345678']);
    workIn($this->tenant);

    expect(resolve(Roster::class)->activeIn($this->plant->id))->toEqual([
        new RosterEntry($ana->id, 'Ana López', null, 'other', $this->production->id, 'Producción', $this->plant->id, true),
        new RosterEntry($zoe->id, 'Zoé Ruiz', 'zoe@acme.mx', 'female', $this->production->id, 'Producción', $this->plant->id, true),
    ]);
});

it('leaves out deactivated people and other work centers', function (): void {
    rosterEmployee($this->tenant, $this->plant, $this->production, ['deactivated_at' => now()]);
    rosterEmployee($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), $this->production);
    workIn($this->tenant);

    expect(resolve(Roster::class)->activeIn($this->plant->id))->toBe([]);
});

it('never lists another company\'s people', function (): void {
    rosterEmployee($this->tenant, $this->plant, $this->production);
    workIn(Tenant::factory()->create());

    expect(resolve(Roster::class)->activeIn($this->plant->id))->toBe([]);
});

it('finds a person by id, deactivated ones too, inside the company only', function (): void {
    $gone = rosterEmployee($this->tenant, $this->plant, $this->production, ['deactivated_at' => now(), 'gender' => Gender::Male]);
    workIn($this->tenant);

    expect(resolve(Roster::class)->find($gone->id)?->active)->toBeFalse()
        ->and(resolve(Roster::class)->find($gone->id)?->gender)->toBe('male')
        ->and(resolve(Roster::class)->find((string) Str::uuid()))->toBeNull();

    workIn(Tenant::factory()->create());

    expect(resolve(Roster::class)->find($gone->id))->toBeNull();
});

it('finds several people at once, by id', function (): void {
    $first = rosterEmployee($this->tenant, $this->plant, $this->production);
    $second = rosterEmployee($this->tenant, $this->plant, $this->production, ['deactivated_at' => now()]);
    workIn($this->tenant);

    $found = resolve(Roster::class)->findMany([$first->id, $second->id, (string) Str::uuid()]);

    expect(array_keys($found))->toEqualCanonicalizing([$first->id, $second->id])
        ->and($found[$second->id]->active)->toBeFalse();
});
