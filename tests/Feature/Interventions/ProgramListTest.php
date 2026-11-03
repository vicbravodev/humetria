<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Http\Controllers\ProgramListController;
use App\Modules\Interventions\Models\Program;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Support\ProgramList;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProgramListController::class, ProgramList::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    workIn($this->tenant);
});

/** A closed evaluation of `$name` with its Programa, drafted from the results. */
function programOf(object $test, string $name, int $day): array
{
    $center = WorkCenter::factory()->for($test->tenant)->create(['name' => $name]);
    [$campaign] = closedEvaluationInAreas($test->tenant, [$name => 6]);
    $campaign->update(['work_center_id' => $center->id, 'closed_at' => "2026-11-{$day} 18:00:00"]);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $item = ProgramItem::query()->where('program_id', $program?->id)->where('scope', 'final')->sole();

    return [$campaign, $program, $item];
}

it('summarizes the Programas by state and lists the closed evaluations, the latest first, each with its Programa', function (): void {
    [$a, $programA, $itemA] = programOf($this, 'Planta Norte', 10);
    [$b, $programB, $itemB] = programOf($this, 'Planta Sur', 12);
    [$c] = programOf($this, 'Oficina', 14);
    ProgramAction::factory()->inProgress()->create(['tenant_id' => $this->tenant->id, 'program_id' => $programA?->id, 'item_id' => $itemA->id, 'due_on' => '2026-11-01']);
    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $programB?->id, 'item_id' => $itemB->id]);
    foreach (ProgramItem::query()->where('program_id', $programB?->id)->where('id', '!=', $itemB->id)->get() as $other) {
        ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $programB?->id, 'item_id' => $other->id]);
    }

    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('interventions/index')
            ->where('summary', ['none' => 1, 'in_progress' => 0, 'overdue' => 1, 'done' => 1])
            ->where('evaluations.total', 3)
            ->where('evaluations.data.0', fn ($row) => $row['id'] === $c->id && $row['workCenter'] === 'Oficina' && $row['closedOn'] === '14 nov 2026' && $row['applies'] === true
                && $row['program']['state'] === ['value' => 'none', 'label' => 'Sin acciones'] && $row['program']['meter'] === ['done' => 0, 'total' => 0])
            ->where('evaluations.data.1.program.state', ['value' => 'done', 'label' => 'Terminado'])
            ->where('evaluations.data.1.program.meter', fn ($meter) => $meter['done'] === $meter['total'] && $meter['total'] > 0)
            ->where('evaluations.data.2.program.state', ['value' => 'overdue', 'label' => 'Con acciones vencidas'])
            ->where('evaluations.data.2.program.meter', ['done' => 0, 'total' => 1]));
    expect([$a->id, $b->id])->not->toBeEmpty();
});

it('lists an evaluation without a Programa, and one that applies only the Guía I', function (): void {
    $center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Taller']);
    $without = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $center->id, 'closed_at' => '2026-11-15 18:00:00']);
    $guideOne = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'instruments' => ['nom035.guide-i'], 'workers' => 10, 'closed_at' => '2026-11-16 18:00:00']);

    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary', ['none' => 0, 'in_progress' => 0, 'overdue' => 0, 'done' => 0])
            ->where('evaluations.data.0', fn ($row) => $row['id'] === $guideOne->id && $row['applies'] === false && $row['program'] === null)
            ->where('evaluations.data.1', fn ($row) => $row['id'] === $without->id && $row['applies'] === true && $row['program'] === null));
});

it('pages the list on the server', function (): void {
    foreach (range(1, 12) as $day) {
        $campaign = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => sprintf('2026-10-%02d 18:00:00', $day)]);
        expect($campaign->id)->not->toBeEmpty();
    }

    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('evaluations.total', 12)->where('evaluations.per_page', 10)->has('evaluations.data', 10));
    $this->actingAs($this->hr)->get(route('tenant.interventions.index', [$this->tenant, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('evaluations.data', 2));
});

it('counts only the Programas of this company', function (): void {
    programOf($this, 'Planta Norte', 10);
    $other = Tenant::factory()->create();
    Program::factory()->create(['tenant_id' => $other->id]);

    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('summary.none', 1)->where('evaluations.total', 1));
});

it('lets owner, RH and the viewer read it, and not the Comisión', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.interventions.index', $this->tenant))->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 200],
    'committee' => [TenantRole::Committee, 403],
]);

it('sends a guest to sign in', function (): void {
    $this->get(route('tenant.interventions.index', $this->tenant))->assertRedirect(route('login'));
});

it('asks the database for a fixed number of queries, however many Programas there are', function (): void {
    foreach (['Uno', 'Dos', 'Tres'] as $index => $name) {
        programOf($this, $name, 10 + $index);
    }

    $measure = function (): int {
        $queries = 0;
        Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))->assertOk();

        return $queries;
    };
    // The first request warms what every page loads (permissions); only then are two requests comparable.
    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))->assertOk();
    $with3 = $measure();
    foreach (['Cuatro', 'Cinco'] as $index => $name) {
        programOf($this, $name, 20 + $index);
    }

    expect($measure())->toBe($with3);
});

it('never carries an individual response in its props', function (): void {
    programOf($this, 'Planta Norte', 10);

    $this->actingAs($this->hr)->get(route('tenant.interventions.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => expectNoIndividualResponses($page->toArray()['props']));
});
