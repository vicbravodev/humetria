<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Results\Support\ResultsPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ResultsPages::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('offers the Programa on the results of an evaluation that asks for one and has none yet', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('program', ['exists' => false, 'state' => null, 'meter' => null]));
});

it('shows where the Programa stands on the results', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));
    workIn($this->tenant);
    $program = resolve(CreateProgram::class)->handle($campaign->id, null);
    $row = ProgramItem::query()->where('program_id', $program?->id)->where('scope', 'final')->sole();
    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $program?->id, 'item_id' => $row->id]);
    ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $program?->id, 'item_id' => $row->id, 'due_on' => '2020-01-01']);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('program', ['exists' => true, 'state' => ['value' => 'overdue', 'label' => 'Con acciones vencidas'], 'meter' => ['done' => 1, 'total' => 2]]));
});

it('shows no Programa card when the norm asks for none, while it is open or for a Guía I campaign', function (Closure $campaign): void {
    $id = $campaign($this->tenant)->id;

    $this->actingAs($this->hr)->get(route('tenant.campaigns.results', [$this->tenant, $id]))
        ->assertInertia(fn (Assert $page) => $page->where('program', null));
})->with([
    'nobody in risk' => fn (Tenant $tenant) => closedEvaluationWith($tenant, nom035HealthyAnswers('nom035.guide-ii')),
    'under the minimum' => fn (Tenant $tenant) => closedEvaluationWith($tenant, nom035Answers('nom035.guide-ii', 'always'), 4),
    'open' => fn (Tenant $tenant) => Campaign::factory()->active()->create(['tenant_id' => $tenant->id]),
    'Guía I' => fn (Tenant $tenant) => Campaign::factory()->trauma()->create(['tenant_id' => $tenant->id]),
]);

it('gives the viewer the card too, since the viewer reads the Programa', function (): void {
    $campaign = closedEvaluationWith($this->tenant, nom035Answers('nom035.guide-ii', 'always'));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.campaigns.results', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('program.exists', false));
});
