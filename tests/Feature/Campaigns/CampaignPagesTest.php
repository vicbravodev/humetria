<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CampaignPages;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CampaignPages::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** Whether `$key` appears as a key anywhere in `$value`. */
function hasKeyDeep(mixed $value, string $key): bool
{
    if (! is_array($value)) {
        return false;
    }

    foreach ($value as $name => $item) {
        if ($name === $key || hasKeyDeep($item, $key)) {
            return true;
        }
    }

    return false;
}

/** A launched campaign of the plant whose `$completed` of `$invited` people answered `$instrument`. */
function campaignWithAnswers(Tenant $tenant, WorkCenter $workCenter, string $instrument, int $invited, int $completed, array $attributes = []): Campaign
{
    $campaign = Campaign::factory()->active('2026-11-14')->create(['tenant_id' => $tenant->id, 'work_center_id' => $workCenter->id, ...$attributes]);

    foreach (range(1, $invited) as $index) {
        $participant = Participant::factory()->create(['campaign_id' => $campaign->id]);
        ParticipantInstrument::factory()->create([
            'participant_id' => $participant->id,
            'instrument' => $instrument,
            ...($index <= $completed ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
        ]);
    }

    return $campaign;
}

it('lists open evaluations with their progress, and each work center with its last evaluation and next due date', function (): void {
    Carbon::setTestNow('2026-10-29 12:00:00');
    // Each participant brings their own person of the plant: 60 people.
    $open = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-iii', 60, 21, [
        'instruments' => ['nom035.guide-i', 'nom035.guide-iii'], 'workers' => 60, 'population' => Population::Sample, 'sample_size' => 52, 'quotas' => ['female' => 52],
    ]);
    $warehouse = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Almacén']);
    staffOf($this->tenant, $warehouse, 20);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $warehouse->id, 'closed_at' => '2024-10-20 18:00:00']);
    WorkCenter::factory()->for($this->tenant)->archived()->create();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/index')
            ->where('campaigns.0', [
                'id' => $open->id,
                'workCenter' => 'Planta Norte',
                'kind' => 'nom035_evaluation',
                'kindLabel' => 'Evaluación NOM-035',
                'status' => 'active',
                'statusLabel' => 'En curso',
                'instruments' => ['Guía I', 'Guía III'],
                'closesOn' => '14 nov 2026',
                'closedOn' => null,
                'progress' => ['completed' => 21, 'target' => 52, 'againstSample' => true],
            ])
            ->where('campaigns.1.status', 'closed')
            ->where('campaigns.1.closedOn', '20 oct 2024')
            ->where('workCenters', [
                [
                    'id' => $warehouse->id, 'name' => 'Almacén', 'workers' => 20, 'guides' => ['I', 'II'],
                    'lastEvaluatedOn' => '20 oct 2024', 'dueOn' => '20 oct 2026', 'overdue' => true,
                    'openCampaignId' => null, 'eligible' => true,
                ],
                [
                    'id' => $this->plant->id, 'name' => 'Planta Norte', 'workers' => 60, 'guides' => ['I', 'III'],
                    'lastEvaluatedOn' => null, 'dueOn' => null, 'overdue' => false,
                    'openCampaignId' => $open->id, 'eligible' => false,
                ],
            ]));
});

it('measures a Guía II evaluation against everyone invited, and a draft not at all', function (): void {
    campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 18, 7);
    $this->travel(1)->minutes();
    Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('campaigns.0.progress', null)
            ->where('campaigns.1.progress', ['completed' => 7, 'target' => 18, 'againstSample' => false]));
});

it('shows a sampled draft with its quotas and who was drawn, to those who manage it only', function (): void {
    staffOf($this->tenant, $this->plant, female: 30, male: 30);
    $this->actingAs($this->hr)->post(route('tenant.campaigns.store', $this->tenant), ['work_center_id' => $this->plant->id, 'population' => 'sample']);
    $campaign = Campaign::query()->withoutGlobalScopes()->sole();

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('campaign.workers', 60)
            ->where('campaign.population', 'sample')
            ->where('campaign.populationLabel', 'Muestra')
            ->where('campaign.sampleSize', 53)
            ->where('campaign.quotas', [
                ['gender' => 'female', 'label' => 'Mujeres', 'count' => 27],
                ['gender' => 'male', 'label' => 'Hombres', 'count' => 26],
            ])
            ->has('sample', 53)
            ->has('sample.0', fn (Assert $person) => $person->hasAll(['id', 'name', 'area', 'gender'])));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sample', null)->where('launch', null));
});

it('offers to launch a draft from tomorrow in Mexico City, suggesting two weeks', function (): void {
    // 03:00 UTC on the 1st is still 31 October in Mexico City.
    Carbon::setTestNow('2026-11-01 03:00:00');
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $draft->id]))
        ->assertInertia(fn (Assert $page) => $page->where('launch', ['min' => '2026-11-01', 'suggested' => '2026-11-14']));
});

it('counts each guide of a launched campaign', function (): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 4, 1);

    $this->actingAs($this->hr)
        ->get(route('tenant.campaigns.show', [$this->tenant, $campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('instruments', [
            ['key' => 'nom035.guide-i', 'title' => 'Guía I', 'invited' => 0, 'completed' => 0, 'cancelled' => 0],
            ['key' => 'nom035.guide-ii', 'title' => 'Guía II', 'invited' => 4, 'completed' => 1, 'cancelled' => 0],
        ]));
});

it('never gives the pages an answer', function (): void {
    $campaign = campaignWithAnswers($this->tenant, $this->plant, 'nom035.guide-ii', 3, 3);
    AnonymousResponse::factory()->create(['campaign_id' => $campaign->id]);

    foreach ([route('tenant.campaigns.index', $this->tenant), route('tenant.campaigns.show', [$this->tenant, $campaign->id])] as $url) {
        $this->actingAs($this->hr)->get($url)->assertInertia(function (Assert $page): void {
            expect(hasKeyDeep($page->toArray()['props'], 'answers'))->toBeFalse();
        });
    }
});
