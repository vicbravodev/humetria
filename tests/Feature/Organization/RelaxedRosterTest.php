<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\CompanyHeadcount;
use App\Modules\Organization\Support\CompanyRoster;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CompanyRoster::class, CompanyHeadcount::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->complete = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id]);
});

/** A person as a union receives them: only what the company's file had. */
function unionPerson(Tenant $tenant, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'work_center_id' => null, 'area_id' => null, 'gender' => null,
        'name' => null, 'email' => null, 'mobile' => null, 'job_title' => null, 'employee_number' => 'E-'.fake()->unique()->numerify('####'),
        ...$attributes,
    ]);
}

it('leaves out people without a center, area, gender, name or job title from the Roster contract', function (array $missing): void {
    $partial = unionPerson($this->tenant, [
        'work_center_id' => $this->plant->id, 'area_id' => $this->area->id, 'gender' => 'female', 'name' => 'Ana López', 'job_title' => 'Operadora',
        ...$missing,
    ]);
    workIn($this->tenant);
    $roster = resolve(Roster::class);

    expect($roster->find($partial->id))->toBeNull()
        ->and($roster->findMany([$partial->id, $this->complete->id]))->toHaveKeys([$this->complete->id])->not->toHaveKey($partial->id)
        ->and(array_map(fn ($entry) => $entry->id, $roster->activeIn($this->plant->id)))->toBe([$this->complete->id])
        ->and($roster->matchingIds(new RosterQuery))->toBe([$this->complete->id])
        ->and(collect($roster->page(new RosterQuery)->items())->pluck('id')->all())->toBe([$this->complete->id]);
})->with([
    'no center' => [['work_center_id' => null]],
    'no area' => [['area_id' => null]],
    'no gender' => [['gender' => null]],
    'no name' => [['name' => null]],
    'no job title' => [['job_title' => null]],
]);

it('keeps a person with all the data and no contact in the Roster contract', function (): void {
    $noContact = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->area->id, 'email' => null, 'mobile' => '5512345678']);
    workIn($this->tenant);

    expect(resolve(Roster::class)->find($noContact->id)?->email)->toBeNull();
});

it('counts headcount without failing on people without a work center', function (): void {
    unionPerson($this->tenant);
    unionPerson($this->tenant, ['area_id' => $this->area->id]);
    workIn($this->tenant);

    expect((new CompanyHeadcount)->byWorkCenter())->toBe([$this->plant->id => 1])
        ->and((new CompanyHeadcount)->areasByWorkCenter([$this->plant->id]))->toBe([$this->plant->id => [['name' => $this->area->name, 'employees' => 1]]]);
});

it('reactivates a person without a work center and announces nothing', function (): void {
    Event::fake([EmployeeReactivated::class]);
    $person = unionPerson($this->tenant, ['deactivated_at' => now()]);
    workIn($this->tenant);

    resolve(ReactivateEmployee::class)->handle($person);

    expect($person->fresh()?->deactivated_at)->toBeNull();
    Event::assertNotDispatched(EmployeeReactivated::class);
});

it('lists a person without a name by their employee number, and without failing on the missing data', function (): void {
    $person = unionPerson($this->tenant, ['employee_number' => 'E-77']);

    $this->actingAs(ownerOf($this->tenant))
        ->get(route('tenant.employees.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/employees')
            ->where('employees.total', 2)
            ->where('employees.data', fn ($rows): bool => collect($rows)->firstWhere('id', $person->id) === [
                'id' => $person->id, 'name' => 'E-77', 'email' => null, 'mobile' => null,
                'gender' => '', 'gender_label' => '', 'work_center_id' => '', 'work_center' => '',
                'area' => '', 'job_title' => '', 'employee_number' => 'E-77', 'deactivated_on' => null,
            ]));
});
