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

// 8 of every page of the panel (company, membership, roles, shared props) and 5 of the page: the counts, the people
// (count and page) with their areas, and the work centers. Nobody on the page has a manager to bring.
const ORG_QUALITY_PAGE_QUERIES = 13;

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

it('shows the gaps of the reporting line to whoever may see the org chart', function (TenantRole $role, int $status, bool $manages): void {
    $response = $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']))->assertStatus($status);

    if ($status === 200) {
        $response->assertInertia(fn (Assert $page) => $page
            ->component('organization/org-chart-quality')
            ->where('can_manage', $manages)
            ->where('issue', 'without_manager')
            ->where('issues', [
                ['value' => 'without_manager', 'label' => 'Sin jefe', 'hint' => 'Personas activas sin jefe directo y sin nadie que les reporte.', 'count' => 1, 'pending' => true],
                ['value' => 'roots', 'label' => 'Raíces', 'hint' => 'Una sola raíz: así debe ser.', 'count' => 1, 'pending' => false],
                ['value' => 'inactive_manager', 'label' => 'Jefe dado de baja', 'hint' => 'Personas activas cuyo jefe directo fue dado de baja.', 'count' => 1, 'pending' => true],
            ])
            ->where('people.total', 1)
            ->where('people.data', [[
                'id' => $this->alone->id,
                'name' => 'Suelta',
                'job_title' => 'Analista',
                'area' => 'Producción',
                'work_center' => 'Planta',
                'manager' => null,
            ]]));
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

it('goes to the first issue with something to fix when none is asked for, so the address names the list', function (): void {
    $quality = fn (string $issue): string => route('tenant.org-chart.quality', [$this->tenant, 'issue' => $issue]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('without_manager'));

    // Nobody is on their own and one root is fine: what is left is the manager who left.
    $this->alone->update(['manager_id' => $this->dir->id]);
    $this->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('inactive_manager'));
    // Something that is not an issue asks for nothing.
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'cycles']))->assertRedirect($quality('inactive_manager'));

    // A second root is something to fix.
    $this->orphan->update(['manager_id' => $this->dir->id]);
    Employee::factory()->reportingTo(Employee::factory()->create([...$this->place, 'name' => 'Otra raíz']))->create($this->place);
    $this->get(route('tenant.org-chart.quality', $this->tenant))->assertRedirect($quality('roots'));
});

it('goes to the first issue when the reporting line is complete', function (): void {
    $this->alone->update(['manager_id' => $this->dir->id]);
    $this->orphan->update(['manager_id' => $this->dir->id]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', $this->tenant))
        ->assertRedirect(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']));
    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'without_manager')
            ->where('issues.0.pending', false)
            ->where('issues.1.pending', false)
            ->where('issues.2.pending', false)
            ->where('people.total', 0));
});

it('opens the issue asked for, each person with their manager as it is saved', function (): void {
    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'inactive_manager']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'inactive_manager')
            ->where('people.total', 1)
            ->where('people.data.0.id', $this->orphan->id)
            ->where('people.data.0.manager', ['value' => $this->gone->id, 'label' => 'Se fue · Gerente']));

    $this->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'roots']))
        ->assertInertia(fn (Assert $page) => $page->where('issue', 'roots')->where('people.data.0.id', $this->dir->id)->where('people.total', 1));
});

it('counts a second root as something to fix', function (): void {
    $other = Employee::factory()->create([...$this->place, 'name' => 'Otra raíz']);
    Employee::factory()->reportingTo($other)->create($this->place);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'roots']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issues.1.count', 2)
            ->where('issues.1.pending', true)
            ->where('issues.1.hint', 'Hay 2 personas hasta arriba. Normalmente solo hay una.')
            ->where('people.total', 2));
});

it('pages the people of an issue, keeping it in the links', function (): void {
    foreach (range(1, 26) as $number) {
        Employee::factory()->reportingTo(Employee::factory()->create([...$this->place, 'name' => sprintf('Raíz %02d', $number)]))->create($this->place);
    }

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'roots', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('issue', 'roots')
            ->where('people.total', 27)
            ->where('people.per_page', 25)
            ->where('people.current_page', 2)
            ->has('people.data', 2)
            ->where('people.prev_page_url', fn (string $url): bool => str_contains($url, 'issue=roots') && str_contains($url, 'page=1')));
});

it('never sends contact data of anyone', function (): void {
    $props = $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'inactive_manager']))->assertOk()->inertiaProps();

    expect(array_keys($props['people']['data'][0]))->toBe(['id', 'name', 'job_title', 'area', 'work_center', 'manager'])
        ->and(array_keys($props['people']['data'][0]['manager']))->toBe(['value', 'label'])
        ->and(json_encode($props))->not->toContain($this->orphan->email, (string) $this->orphan->mobile, $this->gone->email);
});

it('names a person without a name by their company ID', function (): void {
    $this->alone->update(['name' => null, 'employee_number' => 'S-77', 'job_title' => null, 'area_id' => null, 'work_center_id' => null]);

    $this->actingAs($this->viewer)->get(route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']))
        ->assertInertia(fn (Assert $page) => $page->where('people.data.0', ['id' => $this->alone->id, 'name' => 'S-77', 'job_title' => '', 'area' => '', 'work_center' => '', 'manager' => null]));
});

it('reads the page with the same queries whatever the number of rows', function (int $people): void {
    Employee::factory()->count($people - 1)->create($this->place);
    $url = route('tenant.org-chart.quality', [$this->tenant, 'issue' => 'without_manager']);
    $this->actingAs($this->viewer);
    // The first request warms what is cached between requests (roles and permissions).
    $this->get($url)->assertOk();

    $this->expectsDatabaseQueryCount(ORG_QUALITY_PAGE_QUERIES);

    $this->get($url)->assertInertia(fn (Assert $page) => $page->where('people.total', $people));
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
