<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Who needs a clinical assessment after their Guía I is health data about named people (num. 5.5). It is for the
 * company's owner and HR, not for the platform's superadmin acting as one of them.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->campaign = Campaign::factory()->trauma()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $plant->id]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true]);
    $this->owner = ownerOf($this->tenant);
});

function impersonateOwnerOf(Tenant $tenant): void
{
    test()->actingAs(superadmin())->post(route('companies.impersonate', $tenant))->assertRedirect();
}

it('lets the owner see the list of people to channel', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))
        ->assertOk();
});

it('hides the list of people to channel from an impersonating superadmin', function (): void {
    impersonateOwnerOf($this->tenant);

    $this->get(route('tenant.campaigns.screenings', [$this->tenant, $this->campaign->id]))->assertNotFound();
});

it('hides the Guía I counts of the results from an impersonating superadmin', function (): void {
    impersonateOwnerOf($this->tenant);

    $this->get(route('tenant.campaigns.results', [$this->tenant, $this->campaign->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('trauma', null)
            ->where('tenant.can.view_trauma_screenings', false));
});

it('leaves the people to channel out of the dashboard of an impersonating superadmin', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('nextSteps.0.kind', 'screenings'));

    impersonateOwnerOf($this->tenant);

    $this->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('nextSteps', []));
});
