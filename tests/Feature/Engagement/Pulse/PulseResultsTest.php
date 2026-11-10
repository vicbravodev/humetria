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
