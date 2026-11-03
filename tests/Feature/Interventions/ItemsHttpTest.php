<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Http\Controllers\ItemController;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ItemController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    $this->campaign->update(['closed_at' => '2026-11-19 18:00:00']);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
});

function itemUrl(object $test, string $route, ?ProgramItem $item = null): string
{
    return route("tenant.campaigns.program.items.{$route}", $route === 'store' ? [$test->tenant, $test->campaign->id] : [$test->tenant, $test->campaign->id, ($item ?? $test->item)->id]);
}

it('adds a row from the catalog', function (): void {
    $this->actingAs($this->hr)->post(itemUrl($this, 'store'), ['scope' => 'domain', 'scope_key' => 'lack_of_control'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    expect(ProgramItem::query()->where('scope_key', 'lack_of_control')->sole()->label)->toBe('Falta de control sobre el trabajo');
    $this->actingAs($this->hr)->post(itemUrl($this, 'store'), ['scope' => 'domain', 'scope_key' => 'lack_of_control'])->assertSessionHasErrors('scope_key');
    $this->actingAs($this->hr)->post(itemUrl($this, 'store'), ['scope' => 'galaxy', 'scope_key' => 'x'])->assertSessionHasErrors('scope');
    $this->actingAs($this->hr)->post(itemUrl($this, 'store'), ['scope' => 'domain'])->assertSessionHasErrors('scope_key');
});

it('writes the analysis of a row', function (): void {
    $this->actingAs($this->hr)->put(itemUrl($this, 'analysis'), ['analysis' => 'La carga se concentra en producción.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    expect($this->item->fresh()?->analysis)->toBe('La carga se concentra en producción.');
    $this->actingAs($this->hr)->put(itemUrl($this, 'analysis'), ['analysis' => str_repeat('a', 4001)])->assertSessionHasErrors('analysis');
});

it('schedules and records the follow-up evaluation', function (): void {
    $later = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->campaign->work_center_id, 'closed_at' => '2028-11-01 12:00:00']);

    $this->actingAs($this->hr)->put(itemUrl($this, 'follow-up'), ['follow_up_due_on' => '2028-11-19', 'follow_up_done_on' => null, 'follow_up_result' => null, 'follow_up_campaign_id' => null])->assertSessionHasNoErrors();
    expect($this->item->fresh()?->follow_up_due_on?->toDateString())->toBe('2028-11-19');

    $this->actingAs($this->hr)->put(itemUrl($this, 'follow-up'), ['follow_up_due_on' => '2028-11-19', 'follow_up_done_on' => '2026-11-20', 'follow_up_result' => 'Bajó.', 'follow_up_campaign_id' => $later->id])->assertSessionHasNoErrors();
    expect($this->item->fresh()?->follow_up_campaign_id)->toBe($later->id);

    $this->actingAs($this->hr)->put(itemUrl($this, 'follow-up'), ['follow_up_done_on' => '2026-11-20'])->assertSessionHasErrors('follow_up_result');
    $this->actingAs($this->hr)->put(itemUrl($this, 'follow-up'), ['follow_up_due_on' => 'mañana'])->assertSessionHasErrors('follow_up_due_on');
    $this->actingAs($this->hr)->put(itemUrl($this, 'follow-up'), ['follow_up_campaign_id' => 'x'])->assertSessionHasErrors('follow_up_campaign_id');
});

it('lets only owner and RH write', function (TenantRole $role, bool $allowed): void {
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->post(itemUrl($this, 'store'), ['scope' => 'domain', 'scope_key' => 'lack_of_control'])->assertStatus($allowed ? 302 : 403);
    $this->actingAs($person)->put(itemUrl($this, 'analysis'), ['analysis' => 'Texto'])->assertStatus($allowed ? 302 : 403);
    $this->actingAs($person)->put(itemUrl($this, 'follow-up'), ['follow_up_due_on' => '2028-01-01'])->assertStatus($allowed ? 302 : 403);
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('answers 404 for the row of another company or another Programa, and for an evaluation without one', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramItem::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);
    [$second] = closedEvaluationInAreas($this->tenant, ['Logística' => 6]);
    $secondProgram = resolve(CreateProgram::class)->handle($second->id, null);
    $secondItem = ProgramItem::query()->where('program_id', $secondProgram?->id)->firstOrFail();
    $nowhere = '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c';

    $this->actingAs($this->hr);
    $this->put(itemUrl($this, 'analysis', $foreign), ['analysis' => 'x'])->assertNotFound();
    $this->put(itemUrl($this, 'follow-up', $foreign), ['follow_up_due_on' => '2028-01-01'])->assertNotFound();
    $this->put(itemUrl($this, 'analysis', $secondItem), ['analysis' => 'x'])->assertNotFound();
    $this->post(route('tenant.campaigns.program.items.store', [$this->tenant, $nowhere]), ['scope' => 'domain', 'scope_key' => 'lack_of_control'])->assertNotFound();

    expect($secondItem->fresh()?->analysis)->toBeNull();
});

it('shows the analysis, what is missing, the follow-up, the choices to add a row and the day the next evaluation is due', function (): void {
    $this->item->update(['analysis' => 'Hay carga excesiva.', 'follow_up_due_on' => '2028-11-19']);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('program.items.0.analysis', 'Hay carga excesiva.')
        ->where('program.items.0.missingAnalysis', false)
        ->where('program.items.0.followUp', ['dueOn' => '2028-11-19', 'dueOnLabel' => '19 nov 2028', 'doneOn' => null, 'doneOnLabel' => null, 'result' => null, 'evaluation' => null])
        // The other rows of Alto or Muy alto have no analysis yet.
        ->where('program.items.1.missingAnalysis', true)
        ->where('manualOptions', fn ($options) => in_array(['scope' => 'domain', 'key' => 'lack_of_control', 'label' => 'Falta de control sobre el trabajo'], collect($options)->map(fn ($option) => ['scope' => $option['scope'], 'key' => $option['key'], 'label' => $option['label']])->all(), true)
            && ! collect($options)->contains(fn ($option) => $option['scope'] === 'final'))
        ->where('followUpEvaluations', [])
        ->where('followUpSuggestion', ['date' => '2028-11-19', 'label' => '19 nov 2028']));
});

it('offers the evaluations that came later and suggests no day once one has', function (): void {
    $later = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->campaign->work_center_id, 'closed_at' => '2028-11-01 12:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->campaign->work_center_id, 'closed_at' => '2020-11-01 12:00:00']);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('followUpEvaluations', [['id' => $later->id, 'label' => 'Cerrada el 1 nov 2028']])
        ->where('followUpSuggestion', null));
});

it('shows the evaluation that followed, by name', function (): void {
    $later = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->campaign->work_center_id, 'closed_at' => '2028-11-01 12:00:00']);
    $this->item->update(['follow_up_due_on' => '2028-11-19', 'follow_up_done_on' => '2028-11-02', 'follow_up_result' => 'Bajó.', 'follow_up_campaign_id' => $later->id]);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('program.items.0.followUp.result', 'Bajó.')
        ->where('program.items.0.followUp.doneOnLabel', '2 nov 2028')
        ->where('program.items.0.followUp.evaluation', ['id' => $later->id, 'label' => 'Cerrada el 1 nov 2028']));
});

it('gives the viewer the analysis and the follow-up but not the tools to change them', function (): void {
    $this->item->update(['analysis' => 'Hay carga excesiva.']);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('program.items.0.analysis', 'Hay carga excesiva.')
        ->where('manualOptions', null)
        ->where('followUpEvaluations', null)
        ->where('followUpSuggestion', null));
});

it('never carries an individual response in its props', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => expectNoIndividualResponses($page->toArray()['props']));
});
