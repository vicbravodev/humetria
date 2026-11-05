<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Http\Controllers\ScreeningController;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ScreeningController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->campaign = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
});

/** A person of the plant whose Guía I, answered at `$answeredAt`, requires a clinical assessment or not. */
function screened(Campaign $campaign, string $name, string $answeredAt, bool $requires = true, bool $active = true): Employee
{
    $employee = Employee::factory()->create([
        'tenant_id' => $campaign->tenant_id,
        'work_center_id' => $campaign->work_center_id,
        'area_id' => Area::factory()->create(['tenant_id' => $campaign->tenant_id, 'name' => "Área de {$name}"])->id,
        'name' => $name,
        'job_title' => 'Operador',
        ...($active ? [] : ['deactivated_at' => now()]),
    ]);
    TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'employee_id' => $employee->id, 'requires_clinical_assessment' => $requires, 'answered_at' => $answeredAt]);

    return $employee;
}

it('lists who requires a clinical assessment, by when they answered, deactivated people included', function (TenantRole $role): void {
    screened($this->campaign, 'Luis Pérez', '2026-11-05 15:00:00');
    $ana = screened($this->campaign, 'Ana López', '2026-11-03 06:00:00', active: false);
    screened($this->campaign, 'Sin Evento', '2026-11-04 15:00:00', requires: false);

    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('results/screenings')
            ->where('campaign', ['id' => $this->campaign->id, 'workCenter' => 'Planta Norte', 'kindLabel' => 'Guía I por acontecimiento'])
            ->where('counts', ['answered' => 3, 'requiring' => 2])
            ->where('people.total', 2)
            // 06:00 UTC is still the 3rd in Mexico City... at midnight.
            ->where('people.data.0', ['id' => $ana->id, 'name' => 'Ana López', 'area' => 'Área de Ana López', 'jobTitle' => 'Operador', 'answeredOn' => '3 nov 2026', 'referral' => null])
            ->where('people.data.1.name', 'Luis Pérez'));
})->with([TenantRole::Owner, TenantRole::Hr]);

it('gives no answer of the Guía I in the list, only who needs care', function (): void {
    screened($this->campaign, 'Luis Pérez', '2026-11-05 15:00:00');

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => expect(array_keys($page->toArray()['props']['people']['data'][0]))->toBe(['id', 'name', 'area', 'jobTitle', 'answeredOn', 'referral']));
});

it('records in the audit trail who consulted the list', function (): void {
    $hr = memberOf($this->tenant, TenantRole::Hr);

    $this->actingAs($hr)->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]));

    expect(AuditEntry::query()->where('event', 'results.screenings_viewed')->sole())
        ->actor_id->toBe($hr->id)
        ->metadata->toBe(['campaign_id' => $this->campaign->id]);
});

it('hides the list from a viewer, with the same 404 as a list that does not exist', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))
        ->assertNotFound();

    expect(AuditEntry::query()->where('event', 'results.screenings_viewed')->exists())->toBeFalse();
});

it('finds no list of another company\'s campaign', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))
        ->get(route('tenant.campaigns.screenings', [$this->tenant, Campaign::factory()->trauma()->create()->id]))
        ->assertNotFound();
});

it('lists with a fixed number of queries, however many need care', function (int $people): void {
    foreach (range(1, $people) as $number) {
        screened($this->campaign, "Persona {$number}", '2026-11-05 15:00:00');
    }
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $url = route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]);
    $this->get($url);

    // The same whatever the size: nothing is read per row (one more since the redesign, who was channeled already).
    $this->expectsDatabaseQueryCount(21);

    $this->get($url)->assertOk();
})->with([1, 6]);

// Rediseño v2 (board V4-N035-Canalizacion): who waits first, those channeled a click away, and the person chosen open beside
// them, with the five destinations and what to say for each.
it('lists who waits first and who was channeled a click away, and opens the person chosen', function (): void {
    $luis = screened($this->campaign, 'Luis Pérez', '2026-11-05 15:00:00');
    $ana = screened($this->campaign, 'Ana López', '2026-11-03 15:00:00');
    $other = screened($this->campaign, 'Sin Evento', '2026-11-04 15:00:00', requires: false);
    ScreeningReferral::factory()->create(['tenant_id' => $this->tenant->id, 'campaign_id' => $this->campaign->id, 'employee_id' => $ana->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));
    $url = fn (array $query = []): string => route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id, ...$query]);

    $this->get($url())->assertInertia(fn (Assert $page) => $page
        ->where('view', 'pending')
        ->where('people.total', 1)
        ->where('people.data.0.id', $luis->id)
        ->where('selected.id', $luis->id)
        ->where('selected.reachable', true)
        ->where('selected.referral', null)
        ->has('destinations', 5)
        ->where('destinations.0', ['value' => 'imss', 'label' => 'IMSS', 'hint' => 'Su Unidad de Medicina Familiar', 'script' => App\Modules\Results\Enums\Destination::Imss->script(), 'notice' => App\Modules\Results\Enums\Destination::Imss->notice()]));
    $this->get($url(['view' => 'channeled']))->assertInertia(fn (Assert $page) => $page
        ->where('view', 'channeled')
        ->where('people.total', 1)
        ->where('selected.id', $ana->id)
        ->where('selected.referral.destination', 'imss'));
    // Anyone who requires it can be opened from any view; anyone else is not, and the first of the list opens instead.
    $this->get($url(['person' => $ana->id]))->assertInertia(fn (Assert $page) => $page->where('selected.id', $ana->id));
    $this->get($url(['person' => $other->id]))->assertInertia(fn (Assert $page) => $page->where('selected.id', $luis->id));
});

it('opens nobody when nobody waits in the view', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->where('selected', null));
});
