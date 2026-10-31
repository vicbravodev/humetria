<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Http\Controllers\TeleworkController;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\EquipmentItemFactory;
use Database\Factories\Telework\TeleworkDocumentFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Inertia\Testing\AssertableInertia as Assert;

const QUERIES_CENTER = 49;

const QUERIES_INDEX = 39;

mutates(TeleworkController::class, TeleworkPages::class, VisibleCenters::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte', 'main_activity' => 'Manufactura']);
    $this->other = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->viewer = memberOf($this->tenant, TenantRole::Viewer);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

function centerUrl(?WorkCenter $center = null): string
{
    return route('tenant.telework.center', [test()->tenant, ($center ?? test()->center)->id]);
}

/** A person with all their personal data, a place and its review in the test's center. */
function centerPerson(string $name = 'Ana Ruiz', string $review = 'valid'): Teleworker
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id, 'name' => $name, 'job_title' => 'Analista']);
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => test()->tenant->id, 'employee_id' => $employee->id]);
    $place = WorkplaceFactory::new()->create(['tenant_id' => test()->tenant->id, 'teleworker_id' => $person->id]);

    if ($review === 'valid') {
        WorkplaceReviewFactory::new()->create(['tenant_id' => test()->tenant->id, 'workplace_id' => $place->id]);
    }

    return $person;
}

function centerCommittee(): void
{
    SafetyCommittee::factory()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->center->id]);
}

it('shows the empty state with the next step until the committee is registered and the checklist published', function (): void {
    $this->actingAs($this->owner)->get(centerUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/center')
        ->where('nextStep.kind', 'committee')
        ->where('nextStep.title', 'Registra la Comisión')
        ->where('nextStep.href', route('tenant.telework.committee.show', [$this->tenant, $this->center->id])));

    centerCommittee();

    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('nextStep.kind', 'checklist')
        ->where('nextStep.title', 'Que la Comisión publique la lista'));

    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page->where('nextStep', null));
});

it('lists the 17 rows of table 10.3 with their state, detail and where to fix them', function (): void {
    centerPerson();
    centerCommittee();

    $this->actingAs($this->hr)->get(centerUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('workCenter', ['id' => $this->center->id, 'name' => 'Planta Norte', 'activity' => 'Manufactura'])
        ->has('rows', 17)
        ->where('rows.0.numeral', '5.1')
        ->where('rows.0.state', 'done')
        ->where('rows.0.stateLabel', 'Cumple')
        ->where('rows.0.detail', '1 persona en el padrón.')
        ->where('rows.0.href', route('tenant.telework.teleworkers.create', [$this->tenant, $this->center->id]))
        ->where('rows.3.numeral', '5.2')
        ->where('rows.3.state', 'missing')
        ->where('rows.3.href', route('tenant.telework.documents.index', [$this->tenant, 'center' => $this->center->id]))
        ->where('rows.16.numeral', '5.14')
        ->where('progress', ['done' => 7, 'applicable' => 16]));
});

it('counts the progress as done rows over the rows that apply', function (): void {
    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('progress', ['done' => 0, 'applicable' => 10]));
});

it('lists the roster with the derived state of each workplace and, for RH, the 5.1 personal data', function (): void {
    $person = centerPerson();
    WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);

    $this->actingAs($this->hr)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->has('people.data', 1)
        ->where('people.data.0.name', 'Ana Ruiz')
        ->where('people.data.0.jobTitle', 'Analista')
        ->where('people.data.0.percentage', 50)
        ->where('people.data.0.workplaces.0.status', 'valid')
        ->where('people.data.0.workplaces.1.status', 'pending')
        ->where('people.data.0.maritalStatus', 'single')
        ->where('people.data.0.contactPhone', '4421234567')
        ->where('people.data.0.homeAddress', 'Calle Reforma 12, Centro, 76000 Querétaro, Querétaro')
        ->where('people.data.0.href', route('tenant.telework.teleworkers.show', [$this->tenant, $person->id])));
});

it('paginates the roster', function (): void {
    foreach (range(1, 16) as $n) {
        centerPerson("Persona {$n}");
    }

    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('people.total', 16)->where('people.per_page', 15)->has('people.data', 15));
});

it('hides personal data from the viewer: counts and row states only', function (): void {
    centerPerson('Ana Ruiz');
    centerCommittee();

    $response = $this->actingAs($this->viewer)->get(centerUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('people', null)
        ->has('rows', 17)
        ->where('rows.0.href', null)
        ->where('tabs.0.count', 1)
        ->missing('people.data'));
    $props = json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR);

    foreach (['Ana Ruiz', 'Analista', '4421234567', 'Calle Reforma', 'maritalStatus', 'contactPhone', 'homeAddress'] as $private) {
        expect($props)->not->toContain($private);
    }
});

it('shows the committee its center with names but without marital status, phone or home address', function (): void {
    centerPerson('Ana Ruiz');
    centerCommittee();

    $response = $this->actingAs($this->cora)->get(centerUrl())->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('people.data.0.name', 'Ana Ruiz')
        ->where('people.data.0.jobTitle', 'Analista')
        ->where('people.data.0.href', null));
    $props = json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR);

    foreach (['4421234567', 'Calle Reforma', 'maritalStatus', 'contactPhone', 'homeAddress', 'single'] as $private) {
        expect($props)->not->toContain($private);
    }
});

it('answers 404 to the committee for a center it does not sit on', function (): void {
    $this->actingAs($this->cora)->get(centerUrl($this->other))->assertNotFound();
    $this->actingAs($this->owner)->get(centerUrl($this->other))->assertOk();
});

it('answers 404 for a center of another company, an archived one and a made-up one', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->create(['archived_at' => now()]);

    $this->actingAs($this->owner)->get(route('tenant.telework.center', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->actingAs($this->owner)->get(centerUrl($archived))->assertNotFound();
    $this->actingAs($this->owner)->get(route('tenant.telework.center', [$this->tenant, 'not-a-center']))->assertNotFound();
});

it('keeps company A out of company B by address', function (): void {
    $rival = Tenant::factory()->create(['slug' => 'rival']);
    $rivalOwner = memberOf($rival, TenantRole::Owner);

    $this->actingAs($rivalOwner)->get(route('tenant.telework.center', [$rival, $this->center->id]))->assertNotFound();
    $this->actingAs($rivalOwner)->get(route('tenant.telework.center', [$this->tenant, $this->center->id]))->assertNotFound();
});

it('offers each role only the tabs it can open, with counts', function (string $who, array $expected): void {
    centerPerson();
    centerCommittee();
    TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => DocumentKind::Policy]);
    $application = ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => Workplace::query()->firstOrFail()->id]);

    $this->actingAs($this->{$who})->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('tabs', fn ($tabs) => collect($tabs)->map(fn ($tab): string => "{$tab['key']}:{$tab['count']}")->all() === $expected));
})->with([
    'owner' => ['owner', ['people:1', 'documents:1', 'trainings:0', 'notices:0', 'committee:1']],
    'viewer' => ['viewer', ['people:1', 'documents:1']],
    'committee' => ['cora', ['people:1', 'review:1', 'documents:1', 'notices:0', 'committee:1']],
]);

it('asserts the query count of the center page and does not grow with people or documents', function (bool $more): void {
    centerCommittee();
    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    centerPerson();
    // The acknowledgements of the policy and the risk information are read together, once, whatever their number.
    foreach ([DocumentKind::Policy, DocumentKind::RiskInformation] as $kind) {
        TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => $kind]);
    }

    if ($more) {
        foreach (range(1, 6) as $n) {
            $person = centerPerson("Persona {$n}");
            EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
        }
        foreach ([DocumentKind::Reversibility, DocumentKind::MaintenanceProgram, DocumentKind::FamilyViolence] as $kind) {
            TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'kind' => $kind]);
        }
    }

    $this->actingAs($this->owner);
    $this->expectsDatabaseQueryCount(QUERIES_CENTER);

    $this->get(centerUrl())->assertOk();
})->with([false, true]);

it('lists the centers the user may see, with their progress, counts and pending reviews', function (): void {
    centerPerson();
    TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => Workplace::query()->firstOrFail()->id]);

    $this->actingAs($this->hr)->get(route('tenant.telework.index', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/index')
        ->has('centers.data', 2)
        ->where('centers.data.0', fn ($center) => $center['name'] === 'Planta Norte' && $center['teleworkers'] === 1 && $center['aspirants'] === 1 && $center['pendingReviews'] === 1 && $center['done'] === 6 && $center['applicable'] === 16 && $center['activity'] === 'Manufactura')
        ->where('centers.data.1', fn ($center) => $center['name'] === 'Planta Sur' && $center['teleworkers'] === 0 && $center['applicable'] === 10));
});

it('lists to the committee only its centers, and to the viewer every one without names', function (): void {
    centerPerson();

    $this->actingAs($this->cora)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->has('centers.data', 1)->where('centers.data.0.name', 'Planta Norte'));

    $response = $this->actingAs($this->viewer)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->has('centers.data', 2));
    expect(json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR))->not->toContain('Ana Ruiz');
});

it('does not list archived centers and paginates the list', function (): void {
    WorkCenter::factory()->for($this->tenant)->create(['name' => 'Vieja', 'archived_at' => now()]);
    WorkCenter::factory()->for($this->tenant)->count(12)->create();

    $this->actingAs($this->owner)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('centers.total', 14)->where('centers.per_page', 12)->has('centers.data', 12));
    $this->actingAs($this->owner)->get(route('tenant.telework.index', [$this->tenant, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
        ->has('centers.data', 2));
});

it('keeps the other company out of the list', function (): void {
    WorkCenter::factory()->for(Tenant::factory()->create())->create(['name' => 'Ajena']);

    $this->actingAs($this->owner)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('centers.total', 2));
});

it('reads the center list in the same number of queries with two centers or with six', function (int $centers): void {
    // Every center of the page has people, a published list, documents, a pending list and a notice.
    $this->center->update(['name' => 'Planta Norte']);
    $others = WorkCenter::factory()->for($this->tenant)->count($centers - 2)->create();

    foreach ([$this->center, $this->other, ...$others] as $center) {
        $employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $employee->id]);
        $place = WorkplaceFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
        WorkplaceReviewFactory::new()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $place->id]);
        ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => $place->id]);
        EquipmentItemFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
        WorkerNoticeFactory::new()->create(['tenant_id' => $this->tenant->id, 'teleworker_id' => $person->id]);
        SafetyCommittee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id]);
        foreach ([DocumentKind::Policy, DocumentKind::RiskInformation, DocumentKind::Reversibility] as $kind) {
            TeleworkDocumentFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'kind' => $kind]);
        }
    }

    $this->actingAs($this->owner);
    $this->expectsDatabaseQueryCount(QUERIES_INDEX);

    $this->get(route('tenant.telework.index', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page->has('centers.data', $centers));
})->with([2, 6]);
