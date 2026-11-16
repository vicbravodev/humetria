<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\PortalTeamController;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Support\TeamWorkerSection;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalTeamController::class, TeamWorkerSection::class);

// The company of the address and twice its row (whether it has the org chart), the session (the person's state, the person
// and their area), then the person, their manager, their peers (count, page and areas) and their reports (the same three).
const PORTAL_TEAM_QUERIES = 14;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->place = ['tenant_id' => $this->tenant->id, 'area_id' => $this->sales->id];

    // Jefa leads Ana, Beto and Caro; Ana leads Raúl.
    $this->boss = Employee::factory()->create([...$this->place, 'name' => 'Jefa Ruiz', 'job_title' => 'Gerente']);
    $this->ana = Employee::factory()->reportingTo($this->boss)->create([...$this->place, 'name' => 'Ana Sol', 'job_title' => 'Supervisora']);
    $this->beto = Employee::factory()->reportingTo($this->boss)->create([...$this->place, 'name' => 'Beto Paz', 'job_title' => 'Vendedor']);
    $this->caro = Employee::factory()->reportingTo($this->boss)->create([...$this->place, 'name' => 'Caro Luz', 'job_title' => 'Vendedora']);
    $this->raul = Employee::factory()->reportingTo($this->ana)->create([...$this->place, 'name' => 'Raúl Gil', 'job_title' => 'Auxiliar']);
    $this->slug = asPerson($this->ana->id);
});

it('lives at /me/{slug}/team, in the person\'s pages, throttled like the rest of them', function (): void {
    expect(route('portal.team.show', 'acme', absolute: false))->toBe('/me/acme/team')
        ->and(Route::getRoutes()->getByName('portal.team.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

it('sends a visitor to enter, and opens nothing of another company with this session', function (): void {
    session()->flush();
    $this->get(route('portal.team.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));

    $other = Tenant::factory()->create();
    asPerson($this->ana->id);
    $this->get(route('portal.team.show', $other->slug))->assertRedirect(route('worker.home', $other->slug));
});

it('offers «Mi equipo» in the start of any person of a company with the org chart, at their page with no credential', function (): void {
    $section = resolve(TeamWorkerSection::class);
    $ana = new WorkerIdentity($this->tenant->id, $this->ana->id, 'Acme', 'Ana Sol');

    $link = $section->for($ana);

    expect($link?->key)->toBe('team')
        ->and($link?->label)->toBe('Mi equipo')
        ->and($link?->href)->toBe(route('portal.team.show', $this->tenant->slug))
        ->and($link?->pending)->toBeNull();

    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

    expect(resolve(TeamWorkerSection::class)->for($ana))->toBeNull();
});

it('shows the person their manager, their peers and whoever reports to them', function (): void {
    $this->get(route('portal.team.show', $this->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organization/portal-team')
            ->where('slug', $this->tenant->slug)
            ->where('company', 'Acme')
            ->where('name', 'Ana Sol')
            ->where('manager', ['name' => 'Jefa Ruiz', 'job_title' => 'Gerente'])
            ->where('peers.total', 2)
            ->where('peers.per_page', 10)
            ->where('peers.data', [
                ['name' => 'Beto Paz', 'job_title' => 'Vendedor', 'area' => 'Ventas'],
                ['name' => 'Caro Luz', 'job_title' => 'Vendedora', 'area' => 'Ventas'],
            ])
            ->where('reports.total', 1)
            ->where('reports.per_page', 10)
            ->where('reports.data', [['name' => 'Raúl Gil', 'job_title' => 'Auxiliar', 'area' => 'Ventas']]));
});

it('leaves out whoever left, among the peers and the reports', function (): void {
    $this->beto->update(['deactivated_at' => now()]);
    $this->raul->update(['deactivated_at' => now()]);

    $this->get(route('portal.team.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('peers.total', 1)
            ->where('peers.data.0.name', 'Caro Luz')
            ->where('reports.total', 0)
            ->where('reports.data', []));
});

it('shows nobody above nor beside a person with no manager', function (): void {
    // Others with no manager are not their peers.
    Employee::factory()->create([...$this->place, 'name' => 'Otra Sola']);
    asPerson($this->boss->id);

    $this->get(route('portal.team.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Jefa Ruiz')
            ->where('manager', null)
            ->where('peers.total', 0)
            ->where('peers.data', [])
            ->where('reports.total', 3)
            ->where('reports.data.0.name', 'Ana Sol'));
});

it('does not name a manager who left', function (): void {
    $this->boss->update(['deactivated_at' => now()]);

    $this->get(route('portal.team.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page->where('manager', null)->where('peers.total', 2));
});

it('names by their company ID a person without a name, and leaves empty what is not known', function (): void {
    $this->boss->update(['name' => null, 'employee_number' => 'J-1', 'job_title' => null]);
    $this->beto->update(['name' => null, 'employee_number' => 'B-2', 'job_title' => null, 'area_id' => null]);

    $this->get(route('portal.team.show', $this->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('manager', ['name' => 'J-1', 'job_title' => ''])
            // Whoever has no name goes last.
            ->where('peers.data.1', ['name' => 'B-2', 'job_title' => '', 'area' => '']));
});

it('ends the page for a person who left, and answers 404 for a company that does not exist', function (): void {
    $this->ana->update(['deactivated_at' => now()]);

    $this->get(route('portal.team.show', $this->slug))->assertRedirect(route('worker.home', $this->slug));
    $this->get(route('portal.team.show', 'made-up'))->assertNotFound();
});

it('never shows a person of another company the people of this one', function (): void {
    $other = Tenant::factory()->create(['trade_name' => 'Otra']);
    $stranger = Employee::factory()->create(['tenant_id' => $other->id, 'name' => 'Extraña']);

    $props = $this->get(route('portal.team.show', asPerson($stranger->id)))->assertOk()->inertiaProps();

    expect($props['company'])->toBe('Otra')
        ->and($props['manager'])->toBeNull()
        ->and($props['peers']['data'])->toBe([])
        ->and($props['reports']['data'])->toBe([])
        ->and(json_encode($props))->not->toContain('Ana Sol', 'Jefa Ruiz', 'Beto Paz');
});

it('sends no contact data and nobody\'s id', function (): void {
    $props = $this->get(route('portal.team.show', $this->slug))->assertOk()->inertiaProps();
    $sent = json_encode([$props['manager'], $props['peers'], $props['reports'], $props['name'], $props['company']]);

    expect(array_keys($props['manager']))->toBe(['name', 'job_title'])
        ->and(array_keys($props['peers']['data'][0]))->toBe(['name', 'job_title', 'area'])
        ->and(array_keys($props['reports']['data'][0]))->toBe(['name', 'job_title', 'area']);

    foreach ([$this->boss, $this->ana, $this->beto, $this->caro, $this->raul] as $person) {
        expect($sent)->not->toContain($person->id, $person->email, (string) $person->mobile);
    }
});

it('pages the peers and the reports on their own', function (): void {
    Employee::factory()->count(11)->reportingTo($this->boss)->create($this->place);
    Employee::factory()->count(12)->reportingTo($this->ana)->create($this->place);

    $this->get(route('portal.team.show', [$this->slug, 'peers' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('peers.total', 13)
            ->where('peers.current_page', 2)
            ->has('peers.data', 3)
            ->where('peers.prev_page_url', fn (string $url): bool => str_contains($url, 'peers=1'))
            ->where('reports.total', 13)
            ->where('reports.current_page', 1)
            ->has('reports.data', 10)
            // Turning the reports keeps the page of the peers.
            ->where('reports.next_page_url', fn (string $url): bool => str_contains($url, 'reports=2') && str_contains($url, 'peers=2')));
});

it('reads the page with the same queries whatever the size of the team', function (int $people): void {
    Employee::factory()->count($people)->reportingTo($this->boss)->create($this->place);
    Employee::factory()->count($people)->reportingTo($this->ana)->create($this->place);

    $this->expectsDatabaseQueryCount(PORTAL_TEAM_QUERIES);

    $this->get(route('portal.team.show', $this->slug))->assertOk();
})->with([1, 30]);
