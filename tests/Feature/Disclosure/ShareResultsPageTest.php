<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Disclosure\Http\Controllers\ShareResultsController;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Disclosure\Support\SharePages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ShareResultsController::class, SharePages::class);

// Rediseño v2 (board V2-N035-Compartir): «Comparte los resultados con tu equipo», what the people of the center will read
// beside how it reaches them, from the publication that exists (num. 7.8; criterio C-54). The center whole, the minimum
// applied, no name and no answer.

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    staffOf($this->tenant, $this->plant, 20);
    workIn($this->tenant);
});

function shareEvaluation(array $sizes): Campaign
{
    [$campaign] = closedEvaluationInAreas(test()->tenant, $sizes);
    $campaign->update(['work_center_id' => test()->plant->id]);

    return $campaign;
}

function sharePage(Campaign $campaign): Illuminate\Testing\TestResponse
{
    return test()->get(route('tenant.disclosure.results.share', [test()->tenant, $campaign->id]));
}

it('shows what each person will read: how many answered and the themes that most need attention', function (): void {
    $campaign = shareEvaluation(['Producción' => 6, 'Ventas' => 6]);
    $this->actingAs($this->hr);

    sharePage($campaign)->assertInertia(fn (Assert $page) => $page->component('disclosure/share-results')
        ->where('campaign.id', $campaign->id)
        ->where('workCenter', ['id' => $this->plant->id, 'name' => 'Planta Norte'])
        ->where('company', 'Acme')
        ->where('answered', 12)
        ->where('themes', fn ($themes): bool => count($themes) <= 3)
        ->where('published', null)
        ->where('canPublish', true)
        ->has('measures')
        ->has('policy'));
});

it('never gives a count below the minimum: it says «menos de 5» and names no theme', function (): void {
    $campaign = shareEvaluation(['Producción' => 4]);
    $this->actingAs($this->hr);

    sharePage($campaign)->assertInertia(fn (Assert $page) => $page
        ->where('answered', null)
        ->where('minimum', 5)
        ->where('themes', []));
});

it('says when it was published and publishes from here, coming back to it', function (): void {
    $campaign = shareEvaluation(['Producción' => 6]);
    $this->actingAs($this->hr);

    $this->post(route('tenant.disclosure.results.publish', [$this->tenant, $campaign->id]), ['show_measures' => '1', 'back' => 'share'])
        ->assertRedirect(route('tenant.disclosure.results.share', [$this->tenant, $campaign->id]));
    expect(ResultPublication::query()->sole()->show_measures)->toBeTrue();

    sharePage($campaign)->assertInertia(fn (Assert $page) => $page->where('published', '20 nov 2026'));
});

it('is only for whoever manages the disclosure, and a 404 for an evaluation of another company', function (): void {
    $campaign = shareEvaluation(['Producción' => 6]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    sharePage($campaign)->assertForbidden();
    $this->actingAs($this->hr);
    $this->get(route('tenant.disclosure.results.share', [$this->tenant, Campaign::factory()->closed()->create()->id]))->assertNotFound();
});
