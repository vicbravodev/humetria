<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Models\Response;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\PulseComposer;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * A closed pulse of the company in `$mode` with `$count` responses; `$answer` is what each gives to every question that
 * is an agreement (the recommendation, 9).
 */
function pulseResultsSurvey(Tenant $tenant, PulseMode $mode, int $count, int $answer = 5): Survey
{
    $refs = PulseComposer::compose($mode, [Driver::Clarity], 0, true);
    $survey = Survey::factory()->closed()->pulse()->create(['tenant_id' => $tenant->id, 'pulse_mode' => $mode, 'items' => $refs, 'invited' => max($count, 12)]);
    $answers = [];

    foreach ($refs as $ref) {
        $key = explode('@', $ref)[0];
        $answers[$key] = $key === 'eng.recommend' ? 9 : $answer;
    }

    Response::factory()->count($count)->create(['tenant_id' => $tenant->id, 'survey_id' => $survey->id, 'answers' => $answers]);

    return $survey;
}

it('opens the results, the commitments and the managers of a pulse under its own names', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('engagement/results')->where('survey.id', $pulse->id)->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.results.items', [$this->tenant, $pulse]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('engagement/items')->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $pulse]))->assertOk()->assertInertia(fn (Assert $page) => $page->component('engagement/commitments')->where('kind', 'pulse'));
    $this->get(route('tenant.pulse.managers', [$this->tenant, $pulse]))->assertOk();
    $this->get(route('tenant.pulse.export', [$this->tenant, $pulse]))->assertOk();
});

it('does not open a pulse by the climate results, nor a climate survey by the pulse ones', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $climate = climateResultsSurvey($this->tenant, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.climate.results', [$this->tenant, $pulse]))->assertNotFound();
    $this->get(route('tenant.pulse.results', [$this->tenant, $climate]))->assertNotFound();
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $climate]))->assertNotFound();
});

it('tells how many have seen the improvements the company presented, once five have answered', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 12);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction', ['shown' => true, 'favorable' => 100, 'vsPrevious' => null]));
    $this->get(route('tenant.pulse.commitments.index', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction.shown', true)->where('loopAction.favorable', 100));
});

it('gives no number for the improvements seen under five answers, and none for a survey that did not ask', function (): void {
    $few = pulseResultsSurvey($this->tenant, PulseMode::Tour, 4);
    $climate = climateResultsSurvey($this->tenant, 6);
    $climate->update(['items' => array_values(array_filter($climate->items, fn (string $ref): bool => ! str_starts_with($ref, 'loop.action@')))]);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $few]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction', ['shown' => false, 'favorable' => null, 'vsPrevious' => null]));
    $this->get(route('tenant.climate.results', [$this->tenant, $climate]))->assertInertia(fn (Assert $page) => $page->where('loopAction', null));
});

it('compares the improvements seen with the previous pulse', function (): void {
    $before = pulseResultsSurvey($this->tenant, PulseMode::Tour, 40, 2);
    $before->update(['closed_at' => now()->subMonth()]);
    $now = pulseResultsSurvey($this->tenant, PulseMode::Tour, 40, 4);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $now]))->assertInertia(fn (Assert $page) => $page
        ->where('loopAction.vsPrevious', ['points' => 100, 'direction' => 'up']));
});

it('shows only the drivers a pulse asked about', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);
    $this->actingAs($this->hr);

    $this->get(route('tenant.pulse.results', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page
        ->has('drivers', 5)->where('drivers.0.key', 'clarity'));
});
