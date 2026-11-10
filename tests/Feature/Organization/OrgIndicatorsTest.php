<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\OrgIndicators;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OrgIndicators::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->indicators = resolve(OrgIndicators::class);
});

/**
 * Dir is at the top; A leads Ana and Beto; B leads 17 people.
 *
 * @return array<string, Employee>
 */
function indicatorsTree(): array
{
    $place = ['tenant_id' => test()->tenant->id];
    $dir = Employee::factory()->create([...$place, 'name' => 'Dir']);
    $a = Employee::factory()->reportingTo($dir)->create([...$place, 'name' => 'Gte A']);
    $b = Employee::factory()->reportingTo($dir)->create([...$place, 'name' => 'Gte B']);
    $ana = Employee::factory()->reportingTo($a)->create([...$place, 'name' => 'Ana']);
    $beto = Employee::factory()->reportingTo($a)->create([...$place, 'name' => 'Beto']);
    Employee::factory()->count(17)->reportingTo($b)->create($place);

    return ['dir' => $dir, 'a' => $a, 'b' => $b, 'ana' => $ana, 'beto' => $beto];
}

it('tells the levels, the median of direct reports and how many managers to review', function (): void {
    indicatorsTree();

    // Dir and A lead 2 each (few); B leads 17 (many).
    expect($this->indicators->handle())->toBe(['levels' => 3, 'median_direct_reports' => 2.0, 'managers' => 3, 'to_review' => 3]);
});

it('marks only the extremes for review', function (): void {
    $people = indicatorsTree();
    Employee::factory()->reportingTo($people['a'])->create(['tenant_id' => $this->tenant->id]);
    Employee::factory()->count(5)->reportingTo($people['ana'])->create(['tenant_id' => $this->tenant->id]);

    // Dir 2 (few), A 3, Ana 5, B 17 (many): the median of an even number is the average of the two in the middle.
    expect($this->indicators->handle())->toBe(['levels' => 4, 'median_direct_reports' => 4.0, 'managers' => 4, 'to_review' => 2]);
});

it('counts one level and no managers where nobody has a manager', function (): void {
    Employee::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect($this->indicators->handle())->toBe(['levels' => 1, 'median_direct_reports' => null, 'managers' => 0, 'to_review' => 0]);
});

it('counts nothing in an empty company', function (): void {
    Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id]);

    expect($this->indicators->handle())->toBe(['levels' => 0, 'median_direct_reports' => null, 'managers' => 0, 'to_review' => 0]);
});

it('leaves out whoever left', function (): void {
    $people = indicatorsTree();
    $place = ['tenant_id' => $this->tenant->id];
    // Someone who left under Ana is neither a fourth level nor makes her a manager.
    Employee::factory()->reportingTo($people['ana'])->deactivated()->create($place);
    // A manager who left is not a manager: their people start a line of their own (as in the tree).
    $gone = Employee::factory()->reportingTo($people['beto'])->deactivated()->create($place);
    $orphan = Employee::factory()->reportingTo($gone)->create($place);
    Employee::factory()->reportingTo($orphan)->create($place);

    // Dir 2, A 2, B 17 and the orphan 1.
    expect($this->indicators->handle())->toBe(['levels' => 3, 'median_direct_reports' => 2.0, 'managers' => 4, 'to_review' => 4]);
});

it('leaves out another company', function (): void {
    indicatorsTree();
    $other = Employee::factory()->create();
    $below = Employee::factory()->reportingTo($other)->create();
    $deeper = Employee::factory()->reportingTo($below)->create();
    Employee::factory()->reportingTo($deeper)->create();

    expect($this->indicators->handle())->toBe(['levels' => 3, 'median_direct_reports' => 2.0, 'managers' => 3, 'to_review' => 3]);
});

it('reads the indicators with two queries whatever the size of the company', function (bool $people): void {
    if ($people) {
        indicatorsTree();
    }

    $this->expectsDatabaseQueryCount(2);

    $this->indicators->handle();
})->with(['empty' => false, 'with people' => true]);

it('ends on a cycle already in the data', function (): void {
    $people = indicatorsTree();
    // A restore with the trigger disabled: A reports to Ana, who reports to A. Nobody above them is left.
    DB::statement('ALTER TABLE employees DISABLE TRIGGER employees_manager_acyclic');
    DB::table('employees')->where('id', $people['a']->id)->update(['manager_id' => $people['ana']->id]);
    DB::statement('ALTER TABLE employees ENABLE TRIGGER employees_manager_acyclic');

    // Dir leads B alone now; A and Ana still lead (Ana and Beto, and A).
    expect($this->indicators->handle())->toBe(['levels' => 3, 'median_direct_reports' => 1.5, 'managers' => 4, 'to_review' => 4]);
});

it('gives the indicators to the org chart page', function (): void {
    indicatorsTree();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.org-chart.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/org-chart')
            ->where('indicators', ['levels' => 3, 'median_direct_reports' => 2, 'managers' => 3, 'to_review' => 3]));
});
