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

const QUERIES_INDEX_NOTHING_TO_REVIEW = 30;

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

it('links the dossier from the center dashboard only to whoever may open it', function (string $who, bool $linked): void {
    $this->actingAs($this->{$who})->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('dossierHref', $linked ? route('tenant.telework.dossier', [$this->tenant, $this->center->id]) : null));
})->with([
    'owner' => ['owner', true],
    'hr' => ['hr', true],
    'committee' => ['cora', true],
    'viewer' => ['viewer', false],
]);

it('links each row to where each role fixes it, and nowhere for the viewer', function (string $who): void {
    $center = $this->center->id;
    $documents = [route('tenant.telework.documents.index', [$this->tenant, 'center' => $center]), 'Ver documentos'];
    $list = [route('tenant.telework.checklist.show', [$this->tenant, $center]), 'Ver la lista'];
    $reviews = $who === 'cora' ? [route('tenant.telework.reviews.index', [$this->tenant, $center]), 'Ver las listas por revisar'] : [null, null];
    $expected = $who === 'viewer' ? array_fill_keys(['5.1', '5.1.1', '5.1.2', '5.2', '5.3', '5.4 y 7', '5.5', '5.5.1', '5.6', '5.7', '5.8', '5.9 y 8', '5.10', '5.11', '5.12', '5.13', '5.14'], [null, null]) : [
        '5.1' => $who === 'owner' ? [route('tenant.telework.teleworkers.create', [$this->tenant, $center]), 'Registrar persona'] : [null, null],
        '5.1.1' => $reviews,
        '5.1.2' => $reviews,
        '5.2' => $documents,
        '5.3' => $documents,
        '5.4 y 7' => $list,
        '5.5' => $reviews,
        '5.5.1' => $list,
        '5.6' => $documents,
        '5.7' => $who === 'owner' ? [route('tenant.telework.center', [$this->tenant, $center]).'#padron', 'Ver el padrón'] : [null, null],
        '5.8' => $documents,
        '5.9 y 8' => $who === 'owner' ? [route('tenant.telework.trainings.index', [$this->tenant, 'center' => $center]), 'Ver capacitaciones'] : [null, null],
        '5.10' => $documents,
        '5.11' => [route('tenant.telework.notices.index', [$this->tenant, 'center' => $center]), 'Ver los avisos'],
        '5.12' => [route('tenant.telework.committee.show', [$this->tenant, $center]), 'Ver la Comisión'],
        '5.13' => $documents,
        '5.14' => $documents,
    ];

    $this->actingAs($this->{$who})->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('rows', fn ($rows): bool => collect($rows)->mapWithKeys(fn (array $row): array => [$row['numeral'] => [$row['href'], $row['action']]])->all() === $expected));
})->with(['owner', 'cora', 'viewer']);

it('sends each row with its numeral, label, state, state label and detail', function (): void {
    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('rows.0', [
            'numeral' => '5.1',
            'label' => 'Listado de personas teletrabajadoras',
            'state' => 'not_applicable',
            'stateLabel' => 'No aplica',
            'detail' => 'Nadie teletrabaja en este centro todavía.',
            'href' => route('tenant.telework.teleworkers.create', [$this->tenant, $this->center->id]),
            'action' => 'Registrar persona',
        ])
        ->where('teleworkers', 0)
        ->where('aspirants', 0));
});

it('tells each role the next step in full, with a link only for whoever may open it', function (string $who, bool $linked): void {
    $this->actingAs($this->{$who})->get(centerUrl())->assertInertia(fn (Assert $page) => $page->where('nextStep', [
        'kind' => 'committee',
        'title' => 'Registra la Comisión',
        'body' => 'NOM-037 empieza por la Comisión de seguridad e higiene del centro: ella publica la lista de verificación y valida los lugares de trabajo.',
        'cta' => 'Registrar la Comisión',
        'href' => $linked ? route('tenant.telework.committee.show', [$this->tenant, $this->center->id]) : null,
    ]));

    centerCommittee();

    $this->actingAs($this->{$who})->get(centerUrl())->assertInertia(fn (Assert $page) => $page->where('nextStep', [
        'kind' => 'checklist',
        'title' => 'Que la Comisión publique la lista',
        'body' => 'Mientras la lista de verificación no esté publicada, nadie puede contestarla y ningún lugar de trabajo se valida.',
        'cta' => 'Ver la lista',
        'href' => $linked ? route('tenant.telework.checklist.show', [$this->tenant, $this->center->id]) : null,
    ]));
})->with([
    'owner' => ['owner', true],
    'committee' => ['cora', true],
    'viewer' => ['viewer', false],
]);

it('sends each tab with its label, count, link and whether it is the page itself', function (): void {
    centerPerson();
    $center = $this->center->id;

    $this->actingAs($this->cora)->get(centerUrl())->assertInertia(fn (Assert $page) => $page->where('tabs', [
        ['key' => 'people', 'label' => 'Padrón', 'count' => 1, 'href' => centerUrl(), 'active' => true],
        ['key' => 'review', 'label' => 'Por validar', 'count' => 0, 'href' => route('tenant.telework.reviews.index', [$this->tenant, $center]), 'active' => false],
        ['key' => 'documents', 'label' => 'Documentos', 'count' => 0, 'href' => route('tenant.telework.documents.index', [$this->tenant, 'center' => $center]), 'active' => false],
        ['key' => 'notices', 'label' => 'Avisos', 'count' => 0, 'href' => route('tenant.telework.notices.index', [$this->tenant, 'center' => $center]), 'active' => false],
        ['key' => 'committee', 'label' => 'Comisión', 'count' => 1, 'href' => route('tenant.telework.committee.show', [$this->tenant, $center]), 'active' => false],
    ]));
    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('tabs.2', ['key' => 'trainings', 'label' => 'Capacitación', 'count' => 0, 'href' => route('tenant.telework.trainings.index', [$this->tenant, 'center' => $center]), 'active' => false]));
});

it('lists the aspirants after the active people, each with every field and their places as a list', function (): void {
    $ana = centerPerson('Ana Ruiz');
    $beto = centerPerson('Beto Gil', 'none');
    $aspirant = TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ciro Paz', 'job_title' => 'Diseñador'])->id]);
    $anaPlace = Workplace::query()->where('teleworker_id', $ana->id)->firstOrFail();
    $betoPlace = Workplace::query()->where('teleworker_id', $beto->id)->firstOrFail();
    $person = fn (Teleworker $who, string $name, string $job, string $status, array $workplaces, ?string $marital, ?string $phone, ?string $address): array => [
        'id' => $who->id,
        'name' => $name,
        'jobTitle' => $job,
        'percentage' => 50,
        'status' => $status,
        'workplaces' => $workplaces,
        'href' => route('tenant.telework.teleworkers.show', [$this->tenant, $who->id]),
        'maritalStatus' => $marital,
        'contactPhone' => $phone,
        'homeAddress' => $address,
    ];
    $address = 'Calle Reforma 12, Centro, 76000 Querétaro, Querétaro';

    $this->actingAs($this->owner)->get(centerUrl())->assertInertia(fn (Assert $page) => $page
        ->where('teleworkers', 2)
        ->where('aspirants', 1)
        ->where('people.data', [
            $person($ana, 'Ana Ruiz', 'Analista', 'active', [['id' => $anaPlace->id, 'status' => 'valid', 'statusLabel' => 'Validado']], 'single', '4421234567', $address),
            $person($beto, 'Beto Gil', 'Analista', 'active', [['id' => $betoPlace->id, 'status' => 'pending', 'statusLabel' => 'Pendiente de validar']], 'single', '4421234567', $address),
            $person($aspirant, 'Ciro Paz', 'Diseñador', 'aspirant', [], null, null, null),
        ]));
});

it('counts in the list two people, two aspirants and two lists to review in a center, and zero in another', function (): void {
    foreach (['Ana Ruiz', 'Beto Gil'] as $name) {
        ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $this->tenant->id, 'workplace_id' => Workplace::query()->where('teleworker_id', centerPerson($name)->id)->firstOrFail()->id]);
        TeleworkerFactory::new()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id])->id]);
    }

    $this->actingAs($this->owner)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('centers.data.0', ['id' => $this->center->id, 'name' => 'Planta Norte', 'activity' => 'Manufactura', 'teleworkers' => 2, 'aspirants' => 2, 'pendingReviews' => 2, 'done' => 6, 'applicable' => 16])
        ->where('centers.data.1', ['id' => $this->other->id, 'name' => 'Planta Sur', 'activity' => $this->other->main_activity, 'teleworkers' => 0, 'aspirants' => 0, 'pendingReviews' => 0, 'done' => 0, 'applicable' => 10]));
});

it('does not look for whose lists wait for review when none does', function (): void {
    centerPerson();
    $this->actingAs($this->owner);
    $this->expectsDatabaseQueryCount(QUERIES_INDEX_NOTHING_TO_REVIEW);

    $this->get(route('tenant.telework.index', $this->tenant))->assertOk();
});

it('searches the centers by the trimmed text, twenty at most, and pages the list on its own address', function (): void {
    WorkCenter::factory()->for($this->tenant)->count(20)->sequence(fn ($sequence) => ['name' => sprintf('Planta %02d', $sequence->index)])->create();

    $found = $this->actingAs($this->owner)->getJson(route('tenant.telework.work-centers.options', [$this->tenant, 'q' => 'planta']))->assertOk()->json();

    expect($found)->toHaveCount(20)
        ->and(resolve(VisibleCenters::class)->search($this->owner->id, '  planta  '))->toHaveCount(20);
    $this->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('centers.path', route('tenant.telework.index', $this->tenant)));
});
