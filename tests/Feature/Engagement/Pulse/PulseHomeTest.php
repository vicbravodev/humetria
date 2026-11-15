<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-11-11 18:00:00');
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('says the mode of each closed pulse in the history', function (): void {
    $pulse = pulseResultsSurvey($this->tenant, PulseMode::Tour, 6);

    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('surveys.data.0.id', $pulse->id)->where('surveys.data.0.mode', 'Recorrido'));
});

it('has no mode in the history of climate', function (): void {
    climateResultsSurvey($this->tenant, 6);

    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('surveys.data.0.mode', null)->where('howWeAre', null));
});

it('draws how the engagement index moved in the latest four waves, climate and pulse together', function (): void {
    foreach ([5, 4, 3, 2, 1] as $months) {
        $survey = climateResultsSurvey($this->tenant, 6);
        $survey->update(['closed_at' => now()->subMonths($months)]);
    }

    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->has('howWeAre', 4)->where('howWeAre.0.label', 'jul 2026')->where('howWeAre.3.label', 'oct 2026')->where('howWeAre.3.value', 100));
});

it('leaves a wave under the minimum out of how the engagement moved', function (): void {
    $survey = climateResultsSurvey($this->tenant, 4);
    $survey->update(['closed_at' => now()->subMonth()]);

    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('howWeAre', []));
});

it('says in how many days the next pulse goes out, counted in the time of the company', function (string $next, int $days): void {
    App\Modules\Engagement\Models\PulseSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'next_run_on' => $next]);

    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('schedule.daysUntil', $days));
})->with([
    'in four days' => ['2026-11-15', 4],
    'today' => ['2026-11-11', 0],
    'tomorrow' => ['2026-11-12', 1],
]);

it('reads no wave of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    climateResultsSurvey($other, 6);
    workIn($this->tenant);

    $this->actingAs($this->hr)->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('howWeAre', []));
});
