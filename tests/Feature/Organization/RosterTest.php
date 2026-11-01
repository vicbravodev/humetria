<?php

declare(strict_types=1);

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
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

it('lists the active people of a work center by name, with their area, gender and job title', function (): void {
    $zoe = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Zoé Ruiz', 'gender' => Gender::Female, 'email' => 'zoe@acme.mx', 'job_title' => 'Supervisora']);
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'gender' => Gender::Other, 'email' => null, 'mobile' => '5512345678', 'job_title' => 'Operadora']);
    workIn($this->tenant);

    expect(resolve(Roster::class)->activeIn($this->plant->id))->toEqual([
        new RosterEntry($ana->id, 'Ana López', null, 'other', $this->production->id, 'Producción', $this->plant->id, true, 'Operadora'),
        new RosterEntry($zoe->id, 'Zoé Ruiz', 'zoe@acme.mx', 'female', $this->production->id, 'Producción', $this->plant->id, true, 'Supervisora'),
    ]);
});

it('lists only the ids of the active people of a work center', function (): void {
    $kept = rosterEmployee($this->tenant, $this->plant, $this->production);
    rosterEmployee($this->tenant, $this->plant, $this->production, ['deactivated_at' => now()]);
    rosterEmployee($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), $this->production);
    workIn($this->tenant);

    expect(resolve(Roster::class)->activeIdsIn($this->plant->id))->toBe([$kept->id]);
    workIn(Tenant::factory()->create());
    expect(resolve(Roster::class)->activeIdsIn($this->plant->id))->toBe([]);
});

it('lists the ids of the active people of several work centers at once, by center', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $empty = WorkCenter::factory()->for($this->tenant)->create();
    $a = rosterEmployee($this->tenant, $this->plant, $this->production);
    $b = rosterEmployee($this->tenant, $other, $this->production);
    rosterEmployee($this->tenant, $other, $this->production, ['deactivated_at' => now()]);
    workIn($this->tenant);

    expect(resolve(Roster::class)->activeIdsInMany([$this->plant->id, $other->id, $empty->id]))->toBe([$this->plant->id => [$a->id], $other->id => [$b->id], $empty->id => []]);
    workIn(Tenant::factory()->create());
    expect(resolve(Roster::class)->activeIdsInMany([$this->plant->id]))->toBe([$this->plant->id => []]);
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

it('pages the given people by name, deactivated ones too, inside the company only', function (): void {
    $zoe = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Zoé Ruiz']);
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'deactivated_at' => now()]);
    $luis = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Luis Mora']);
    rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Berta, no pedida']);
    $other = Tenant::factory()->create();
    $outsider = rosterEmployee($other, WorkCenter::factory()->for($other)->create(), Area::factory()->create(['tenant_id' => $other->id]), ['name' => 'Alguien de otra empresa']);
    workIn($this->tenant);

    $page = resolve(Roster::class)->page(new RosterQuery(employeeIds: [$zoe->id, $ana->id, $luis->id, $outsider->id]), perPage: 2);

    expect($page->total())->toBe(3)
        ->and($page->lastPage())->toBe(2)
        ->and(array_map(fn (RosterEntry $person): string => $person->name, $page->items()))->toBe(['Ana López', 'Luis Mora']);
});

it('pages the active people of a work center when asked for one', function (): void {
    rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López']);
    rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Baja', 'deactivated_at' => now()]);
    rosterEmployee($this->tenant, WorkCenter::factory()->for($this->tenant)->create(), $this->production, ['name' => 'Otro centro']);
    workIn($this->tenant);

    $page = resolve(Roster::class)->page(new RosterQuery(activeInWorkCenter: $this->plant->id));

    expect(array_map(fn (RosterEntry $person): string => $person->name, $page->items()))->toBe(['Ana López']);
});

it('narrows the page by name, email or number, and by area', function (): void {
    $sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $luis = rosterEmployee($this->tenant, $this->plant, $sales, ['name' => 'Luis Mora', 'email' => 'lmora@acme.mx', 'employee_number' => 'E-77']);
    $percent = rosterEmployee($this->tenant, $this->plant, $sales, ['name' => '100% Ventas', 'email' => null]);
    workIn($this->tenant);
    $names = fn (RosterQuery $query): array => array_map(fn (RosterEntry $person): string => $person->name, resolve(Roster::class)->page($query)->items());
    $ids = [$ana->id, $luis->id, $percent->id];

    expect($names(new RosterQuery(employeeIds: $ids, search: 'LÓP')))->toBe(['Ana López'])
        ->and($names(new RosterQuery(employeeIds: $ids, search: 'lmora@')))->toBe(['Luis Mora'])
        ->and($names(new RosterQuery(employeeIds: $ids, search: 'e-77')))->toBe(['Luis Mora'])
        ->and($names(new RosterQuery(employeeIds: $ids, search: '%')))->toBe(['100% Ventas'])
        ->and($names(new RosterQuery(employeeIds: $ids, areaId: $sales->id)))->toBe(['100% Ventas', 'Luis Mora'])
        ->and($names(new RosterQuery(employeeIds: [])))->toBe([]);
});

it('gives the ids of everyone a query matches, without paging', function (): void {
    // Fixed emails: the search also reads them, and a random one could contain «mora».
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $luis = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Luis Mora', 'email' => 'luis@acme.mx']);
    rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Baja', 'email' => 'baja@acme.mx', 'deactivated_at' => now()]);
    workIn($this->tenant);

    expect(resolve(Roster::class)->matchingIds(new RosterQuery(activeInWorkCenter: $this->plant->id)))->toBe([$ana->id, $luis->id])
        ->and(resolve(Roster::class)->matchingIds(new RosterQuery(activeInWorkCenter: $this->plant->id, search: 'mora')))->toBe([$luis->id]);
});

it('searches ignoring accents and case', function (): void {
    $jose = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'José Núñez']);
    workIn($this->tenant);

    expect(resolve(Roster::class)->matchingIds(new RosterQuery(employeeIds: [$jose->id], search: 'jose nunez')))->toBe([$jose->id])
        ->and(resolve(Roster::class)->matchingIds(new RosterQuery(employeeIds: [$jose->id], search: 'NÚÑ')))->toBe([$jose->id]);
});

it('takes any number of ids, past what Postgres binds one by one', function (): void {
    $ana = rosterEmployee($this->tenant, $this->plant, $this->production, ['name' => 'Ana López']);
    workIn($this->tenant);
    $ids = [...array_map(fn (): string => (string) Str::uuid(), range(1, 70_000)), $ana->id, 'not-a-uuid'];

    expect(resolve(Roster::class)->page(new RosterQuery(employeeIds: $ids))->total())->toBe(1)
        ->and(resolve(Roster::class)->matchingIds(new RosterQuery(employeeIds: $ids)))->toBe([$ana->id])
        ->and(array_keys(resolve(Roster::class)->findMany($ids)))->toBe([$ana->id]);
});
