<?php

declare(strict_types=1);

use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Support\TeleworkPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\ChecklistApplicationFactory;
use Database\Factories\Telework\ChecklistFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Database\Factories\Telework\WorkerNoticeFactory;
use Database\Factories\Telework\WorkplaceFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(TeleworkPages::class);

// Rediseño v2, board V2-Comision (decisión A5: the Comisión keeps its account): a member of the Comisión sees only its
// queue — what waits for it in the centers it sits on — and who sits with it.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Querétaro']);
    $this->elsewhere = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Monterrey']);
    $this->ruben = memberOf($this->tenant, TenantRole::Committee);
    $this->ruben->update(['name' => 'Rubén Ortiz']);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'user_id' => $this->ruben->id]);
    // The center has its checklist published: what blocks the Comisión is tested apart.
    $this->published = ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
});

/** A person of the center whose list of their place waits for the Comisión. */
function waitingList(WorkCenter $center, string $name): void
{
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $center->tenant_id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => $name])->id]);
    ChecklistApplicationFactory::new()->submitted()->create(['tenant_id' => $center->tenant_id, 'workplace_id' => WorkplaceFactory::new()->create(['tenant_id' => $center->tenant_id, 'teleworker_id' => $person->id])->id]);
    WorkerNoticeFactory::new()->create(['tenant_id' => $center->tenant_id, 'teleworker_id' => $person->id]);
}

it('shows a member of the Comisión only what waits for it in its centers, and who sits with it', function (): void {
    waitingList($this->plant, 'Hugo Saiz');
    waitingList($this->plant, 'Manuel Castellano');
    waitingList($this->elsewhere, 'Ana Ruiz');
    $olga = memberOf($this->tenant, TenantRole::Owner);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'user_id' => $olga->id]);

    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->component('telework/queue')
        ->where('name', 'Rubén Ortiz')
        ->where('tasks', [
            ['kind' => 'lists', 'workCenter' => 'Planta Querétaro', 'count' => 2, 'people' => ['Hugo Saiz', 'Manuel Castellano'], 'href' => route('tenant.telework.reviews.index', [$this->tenant, $this->plant->id])],
            ['kind' => 'notices', 'workCenter' => 'Planta Querétaro', 'count' => 2, 'people' => [], 'href' => route('tenant.telework.notices.index', [$this->tenant, 'center' => $this->plant->id])],
        ])
        ->where('centers', [['id' => $this->plant->id, 'name' => 'Planta Querétaro', 'members' => collect([$olga->name, 'Rubén Ortiz'])->sort()->values()->all(), 'href' => route('tenant.telework.route', [$this->tenant, $this->plant->id])]]));
});

// Rediseño v2 (PR 3): what waits for the Comisión in Ley Silla, read through Seating's contract — the assessments to sign,
// with whose they are, and the signed ones still out of the minutes of the walk-through.
it('lists the Ley Silla assessments the Comisión has to sign and to record in its minutes', function (): void {
    $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id];
    $position = StandingPosition::factory()->create($where);

    foreach (['José Antonio', 'Óscar Peña'] as $name) {
        RiskAssessment::factory()->create($where + ['position_id' => $position->id, 'employee_id' => EmployeeFactory::new()->create($where + ['name' => $name])->id]);
    }

    RiskAssessment::factory()->signed()->create($where + ['position_id' => $position->id]);
    RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->elsewhere->id]);

    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->component('telework/queue')
        ->where('tasks', [
            ['kind' => 'signatures', 'workCenter' => 'Planta Querétaro', 'count' => 2, 'people' => ['José Antonio', 'Óscar Peña'], 'href' => route('tenant.seating.assessments.index', [$this->tenant, $this->plant->id])],
            ['kind' => 'minutes', 'workCenter' => 'Planta Querétaro', 'count' => 1, 'people' => [], 'href' => route('tenant.seating.assessments.index', [$this->tenant, $this->plant->id])],
        ]));
});

// F2 (C-11): what blocks the Comisión shows in its queue, in its centers only.
it('tells the Comisión that its center has no checklist published, until it is, and only when NOM-037 applies there', function (): void {
    $person = TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id])->id]);
    $this->published->delete();
    $href = route('tenant.telework.checklist.show', [$this->tenant, $this->plant->id]);

    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('tasks', [['kind' => 'checklist', 'workCenter' => 'Planta Querétaro', 'count' => 1, 'people' => [], 'href' => $href]]));

    ChecklistFactory::new()->published()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('tasks', []));

    expect($person->exists)->toBeTrue();
});

it('does not ask for a checklist where nobody teleworks and nothing was declared, nor in the centers of others', function (): void {
    $this->published->delete();
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->elsewhere->id])->id]);

    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('tasks', []));
});

it('tells the Comisión how many people who work standing have no assessment, in its centers only', function (): void {
    foreach ([$this->plant, $this->elsewhere] as $center) {
        $where = ['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id];
        $position = StandingPosition::factory()->create($where);

        foreach (range(1, 3) as $ignored) {
            PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'position_id' => $position->id, 'employee_id' => EmployeeFactory::new()->create($where)->id]);
        }
    }

    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('tasks', [['kind' => 'unassessed', 'workCenter' => 'Planta Querétaro', 'count' => 3, 'people' => [], 'href' => route('tenant.seating.assessments.index', [$this->tenant, $this->plant->id])]]));
});

it('has nothing to ask of a Comisión with nothing waiting', function (): void {
    $this->actingAs($this->ruben)->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->component('telework/queue')->where('tasks', [])->has('centers', 1));
});

it('keeps showing RH and the viewer the centers', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.telework.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->component('telework/index'));
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('reads the queue in a fixed number of queries', function (): void {
    waitingList($this->plant, 'Hugo Saiz');
    waitingList($this->plant, 'Manuel Castellano');
    $this->actingAs($this->ruben);

    // 25 for NOM-037, 3 for what waits in Ley Silla (rediseño v2, PR 3), 12 more for the facts of its requirement R2 (people
    // without assessment, read once for all the centers) and 3 for the checklist that blocks the Comisión (F2).
    $this->expectsDatabaseQueryCount(43);
    $this->get(route('tenant.telework.index', $this->tenant))->assertOk();
});
