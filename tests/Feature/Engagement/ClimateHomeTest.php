<?php

declare(strict_types=1);

use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

// The home of Clima closes the cycle in one screen: the survey in progress, the previous result and its commitments.

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create());
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

it('shows the latest closed climate with its indices and how its commitments go', function (): void {
    $older = climateResultsSurvey($this->tenant, 6);
    $older->update(['closed_at' => now()->subMonths(6)]);
    $latest = climateResultsSurvey($this->tenant, 40);
    $latest->update(['closed_at' => now()->subMonth()]);
    foreach ([CommitmentStatus::Open, CommitmentStatus::InProgress, CommitmentStatus::Done, CommitmentStatus::Done, CommitmentStatus::Dropped] as $status) {
        Commitment::factory()->create(['tenant_id' => $this->tenant->id, 'survey_id' => $latest->id, 'status' => $status]);
    }

    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('latest.id', $latest->id)
        ->where('latest.name', $latest->name)
        ->where('latest.index', 100)
        ->where('latest.recommendation', 100)
        ->where('latest.commitments', ['open' => 1, 'in_progress' => 1, 'done' => 2]));
});

it('keeps the figures of a latest climate under the minimum hidden', function (): void {
    climateResultsSurvey($this->tenant, 4);

    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('latest.index', null)->where('latest.recommendation', null));
});

it('has no latest climate before one closes, and none on the pulse home', function (): void {
    climateResultsSurvey($this->tenant, 6, closed: false);

    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('latest', null));
    $this->get(route('tenant.pulse.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('latest', null));
});

it('reads no climate of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    climateResultsSurvey($other, 6);
    workIn($this->tenant);

    $this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('latest', null));
});

it('puts no individual response in the props of the home', function (): void {
    climateResultsSurvey($this->tenant, 12);

    expectNoEngagementResponses($this->actingAs($this->hr)->get(route('tenant.climate.index', $this->tenant))->inertiaProps());
});
