<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Http\Controllers\OrgChartQualityController;
use App\Modules\Organization\Http\Requests\ManagerRequest;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OrgChartQualityController::class, ManagerRequest::class);

// 8 of every page of the panel (company, membership, roles, shared props) and 7 of the page: the counts, the levels of
// the chart (2), the people (count and page) with their areas, and the work centers. Nobody on the page has a manager to bring. Changed by the redesign of the list (everybody, search and the facts under the title): 13 before it.
const ORG_QUALITY_PAGE_QUERIES = 15;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta']);
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->place = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'area_id' => $this->production->id];

    // Dir leads Ana; Suelta is on her own; Huérfana's manager left.
    $this->dir = Employee::factory()->create([...$this->place, 'name' => 'Dir', 'job_title' => 'Directora']);
    $this->ana = Employee::factory()->reportingTo($this->dir)->create([...$this->place, 'name' => 'Ana']);
    $this->alone = Employee::factory()->create([...$this->place, 'name' => 'Suelta', 'job_title' => 'Analista']);
    $this->gone = Employee::factory()->reportingTo($this->dir)->deactivated()->create([...$this->place, 'name' => 'Se fue', 'job_title' => 'Gerente']);
    $this->orphan = Employee::factory()->reportingTo($this->gone)->create([...$this->place, 'name' => 'Huérfana']);
});

it('shows the list of the reporting line to whoever may see the org chart', function (TenantRole $role, int $status, bool $manages): void {
    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager']))->assertStatus($status);

    if ($status === 200) {
        $response->assertInertia(fn (Assert $page) => $page
            ->component('organization/org-chart-quality')
            ->where('can_manage', $manages)
            ->where('issue', 'no_manager')
            ->where('issues', [
                ['value' => 'all', 'label' => 'Todas', 'count' => 4, 'pending' => false],
                ['value' => 'no_manager', 'label' => 'Sin jefe', 'count' => 2, 'pending' => true],
                ['value' => 'many_reports', 'label' => 'Más de 15 directos', 'count' => 0, 'pending' => false],
                ['value' => 'inactive_manager', 'label' => 'Jefe dado de baja', 'count' => 1, 'pending' => true],
            ])
            ->where('total_people', 4)
            ->where('with_manager', 2)
            ->where('levels', 2)
            ->where('filters', ['q' => '', 'work_center' => null])
            ->where('people.total', 2)
            ->where('people.data', [
                ['id' => $this->dir->id, 'name' => 'Dir', 'job_title' => 'Directora', 'area' => 'Producción', 'work_center' => 'Planta', 'manager' => null, 'manager_inactive' => false, 'direct_reports' => 1],
                ['id' => $this->alone->id, 'name' => 'Suelta', 'job_title' => 'Analista', 'area' => 'Producción', 'work_center' => 'Planta', 'manager' => null, 'manager_inactive' => false, 'direct_reports' => 0],
            ]));
    }
})->with([
    'owner' => [TenantRole::Owner, 200, true],
    'hr' => [TenantRole::Hr, 200, true],
    'viewer' => [TenantRole::Viewer, 200, false],
    'committee' => [TenantRole::Committee, 403, false],
]);

it('is not for outsiders nor for a union account', function (): void {
    $this->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect(route('login'));
    $this->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->alone]), ['manager_id' => $this->dir->id])->assertRedirect(route('login'));

    $union = Tenant::factory()->union()->create();
    $person = Employee::factory()->create(['tenant_id' => $union->id]);

    $this->actingAs(memberOf($union, TenantRole::Owner))->get(route('tenant.org-chart.quality', $union))->assertNotFound();
    $this->put(route('tenant.org-chart.manager.update', [$union, $person]), ['manager_id' => ''])->assertNotFound();
});

it('goes to the first view with something to fix when none is asked for, so the address names the list', function (): void {
    $quality = fn (string $issue): string => route('tenant.org-chart.quality', [$this->tenant, 'issue' => $issue]);

    // Two people with nobody above (Dir and Suelta) are something to fix.
    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('no_manager'));

    // One at the top is fine: what is left is the manager who left.
    $this->alone->update(['manager_id' => $this->dir->id]);
    $this->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('inactive_manager'));
    // Something that is not a view asks for nothing.
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'cycles']))->assertRedirect($quality('inactive_manager'));

    // A manager with more than 15 people comes before the one who left.
    Employee::factory()->count(16)->reportingTo($this->ana)->create($this->place);
    $this->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('many_reports'));
});

it('goes to everybody when the reporting line is complete', function (): void {
    $this->alone->update(['manager_id' => $this->dir->id]);
    $this->orphan->update(['manager_id' => $this->dir->id]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', $this->tenant))
        ->assertRedirect(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all']));
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'all')
            ->where('issues.1.pending', false)
            ->where('issues.2.pending', false)
            ->where('issues.3.pending', false)
            ->where('people.total', 4));
});

it('lists everybody with whoever has nobody above first', function (): void {
    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.total', 4)
            ->where('people.data.0.name', 'Dir')
            ->where('people.data.1.name', 'Suelta')
            ->where('people.data.2.name', 'Ana')
            ->where('people.data.3.name', 'Huérfana'));
});

it('opens the view asked for, each person with their manager as it is saved and how many report to them', function (): void {
    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'inactive_manager']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'inactive_manager')
            ->where('people.total', 1)
            ->where('people.data.0.id', $this->orphan->id)
            ->where('people.data.0.direct_reports', 0)
            ->where('people.data.0.manager_inactive', true)
            ->where('people.data.0.manager', ['value' => $this->gone->id, 'label' => 'Se fue · Gerente']));

    // A manager who is still here is not marked.
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people.data.2.name', 'Ana')
            ->where('people.data.2.manager_inactive', false));

    Employee::factory()->count(16)->reportingTo($this->ana)->create($this->place);
    Employee::factory()->count(15)->reportingTo($this->dir)->create($this->place);
    Employee::factory()->count(3)->reportingTo($this->alone)->deactivated()->create($this->place);

    // Ana leads 16; Dir leads Ana and 15 more (the one who left does not count); Suelta's three who left do not count.
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'many_reports']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issues.2.count', 2)
            ->where('issues.2.pending', true)
            ->where('people.total', 2)
            ->where('people.data.0.name', 'Ana')
            ->where('people.data.0.direct_reports', 16)
            ->where('people.data.1.name', 'Dir')
            ->where('people.data.1.direct_reports', 16));
});

it('says that one person at the top is fine and a second one is something to fix', function (): void {
    $this->alone->update(['manager_id' => $this->dir->id]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager']))
        ->assertInertia(fn (Assert $page) => $page->where('issues.1.count', 1)->where('issues.1.pending', false));

    Employee::factory()->create([...$this->place, 'name' => 'Otra raíz']);

    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager']))
        ->assertInertia(fn (Assert $page) => $page->where('issues.1.count', 2)->where('issues.1.pending', true)->where('people.total', 2));
});

it('searches by name or employee number without caring about case or accents, and narrows to a work center', function (): void {
    $north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Norte']);
    $this->alone->update(['work_center_id' => $north->id, 'name' => 'Ángela Suelta']);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all', 'q' => ' ANGELA ']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['q' => 'ANGELA', 'work_center' => null])
            ->where('people.total', 1)
            ->where('people.data.0.id', $this->alone->id));

    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all', 'work_center' => $north->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.work_center', ['value' => $north->id, 'label' => 'Norte'])
            ->where('people.total', 1)
            ->where('people.data.0.id', $this->alone->id));

    // Something that is not an id narrows nothing.
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all', 'work_center' => 'norte']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.work_center', null)->where('people.total', 4));
});

it('pages the people of a view, keeping it and the search in the links', function (): void {
    Employee::factory()->count(26)->create([...$this->place, 'name' => 'Raíz']);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager', 'q' => 'raíz', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'no_manager')
            ->where('people.total', 26)
            ->where('people.per_page', 25)
            ->where('people.current_page', 2)
            ->has('people.data', 1)
            ->where('people.prev_page_url', fn (string $url): bool => str_contains($url, 'issue=no_manager') && str_contains($url, 'page=1') && str_contains($url, 'q=ra')));
});

it('never sends contact data of anyone', function (): void {
    $props = $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'inactive_manager']))->assertOk()->inertiaProps();

    expect(array_keys($props['people']['data'][0]))->toBe(['id', 'name', 'job_title', 'area', 'work_center', 'manager', 'manager_inactive', 'direct_reports'])
        ->and(array_keys($props['people']['data'][0]['manager']))->toBe(['value', 'label'])
        ->and(json_encode($props))->not->toContain($this->orphan->email, (string) $this->orphan->mobile, $this->gone->email);
});

it('names a person without a name by their company ID', function (): void {
    $this->alone->update(['name' => null, 'employee_number' => 'S-77', 'job_title' => null, 'area_id' => null, 'work_center_id' => null]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager', 'q' => 'S-77']))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0', ['id' => $this->alone->id, 'name' => 'S-77', 'job_title' => '', 'area' => '', 'work_center' => '', 'manager' => null, 'manager_inactive' => false, 'direct_reports' => 0]));
});

it('does not list the people of another company', function (): void {
    Employee::factory()->create(['name' => 'Ajena']);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 4)->where('total_people', 4));
});

it('reads the page with the same queries whatever the number of rows', function (int $people): void {
    Employee::factory()->count($people - 1)->create($this->place);
    $url = route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'no_manager']);
    $this->actingAs($this->viewer);
    // The first request warms what is cached between requests (roles and permissions).
    $this->get($url)->assertOk();

    $this->expectsDatabaseQueryCount(ORG_QUALITY_PAGE_QUERIES);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('people.total', $people + 1));
})->with([3, 30]);

it('lets only whoever manages the org chart fix a manager', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->alone]), ['manager_id' => $this->dir->id])
        ->assertForbidden();

    expect($this->alone->refresh()->manager_id)->toBeNull();
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('fixes the manager of a person in place, to the audit trail', function (): void {
    $list = route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']);

    // Back to the list it was fixed on, though it may be empty now.
    $this->actingAs($this->hr)->from($list)
        ->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->alone]), ['manager_id' => $this->dir->id])
        ->assertRedirect($list)
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Jefe actualizado.']]);

    expect($this->alone->refresh()->manager_id)->toBe($this->dir->id)
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->sole()->metadata)
        ->toEqual(['from_manager_id' => null, 'to_manager_id' => $this->dir->id]);
});

it('leaves a person with no manager when none is sent', function (array $form): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->orphan]), $form)
        ->assertSessionHasNoErrors();

    expect($this->orphan->refresh()->manager_id)->toBeNull();
})->with(['empty' => [['manager_id' => '']], 'missing' => [[]]]);

it('rejects a manager who may not be it, and changes nothing', function (Closure $manager, string $message): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->dir]), ['manager_id' => $manager($this)])
        ->assertSessionHasErrors(['manager_id' => $message]);

    expect($this->dir->refresh()->manager_id)->toBeNull()
        ->and(AuditEntry::query()->where('event', 'employee.manager_changed')->count())->toBe(0);
})->with([
    'someone who reports to them' => [fn ($test) => $test->ana->id, 'Esa persona le reporta, directa o indirectamente. Elige a alguien más.'],
    'themselves' => [fn ($test) => $test->dir->id, 'Una persona no puede ser su propio jefe.'],
    'someone who left' => [fn ($test) => $test->gone->id, 'Esa persona está dada de baja.'],
    'another company' => [fn () => Employee::factory()->create()->id, 'Elige a una persona de la lista.'],
    'not an id' => [fn () => 'ana', 'El campo jefe directo debe ser un UUID válido.'],
]);

it('does not find a person of another company, nor fixes one who left', function (): void {
    $this->actingAs($this->hr)
        ->put(route('tenant.org-chart.manager.update', [$this->tenant, Employee::factory()->create()]), ['manager_id' => $this->dir->id])
        ->assertNotFound();
    $this->put(route('tenant.org-chart.manager.update', [$this->tenant, $this->gone]), ['manager_id' => ''])->assertConflict();

    expect($this->gone->refresh()->manager_id)->toBe($this->dir->id);
});
