<?php

declare(strict_types=1);

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Support\CompanyRiskResults;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

mutates(CompanyRiskResults::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->production = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->sales = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ventas']);
    $this->closed = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'workers' => 30]);
    workIn($this->tenant);
});

/** `$count` anonymous Guía II questionnaires of the campaign in `$area`, all answered `$value`. */
function anonymousGuideTwo(Campaign $campaign, Area $area, int $count, string $value = 'never'): void
{
    AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'area_id' => $area->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', $value)]);
}

function breakdownOf(Campaign $campaign, ?string $areaId = null): RiskBreakdown
{
    $record = resolve(CampaignRecords::class)->find($campaign->id);
    expect($record)->toBeInstanceOf(CampaignRecord::class);

    return resolve(RiskResults::class)->forCampaign($record, $areaId);
}

it('counts the people of a closed evaluation in each risk level, by category and domain, never as an average', function (): void {
    anonymousGuideTwo($this->closed, $this->production, 6, 'never');

    $risk = breakdownOf($this->closed);

    expect($risk->state)->toBe(RiskBreakdown::AVAILABLE)
        ->and($risk->answered)->toBe(6)
        ->and($risk->final)->toBe([0, 0, 6, 0, 0])
        ->and($risk->categories)->not->toBeEmpty()
        ->and($risk->domains)->not->toBeEmpty()
        ->and(array_sum($risk->domains[0]->levels))->toBe(6);
});

it('shows a group of five questionnaires and hides one of four', function (): void {
    anonymousGuideTwo($this->closed, $this->production, 4);
    expect(breakdownOf($this->closed))->toEqual(RiskBreakdown::belowMinimum());

    anonymousGuideTwo($this->closed, $this->production, 1);
    expect(breakdownOf($this->closed)->state)->toBe(RiskBreakdown::AVAILABLE);
});

it('shows no figure while the evaluation is open, however many people answered', function (): void {
    $open = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    anonymousGuideTwo($open, $this->production, 10);

    expect(breakdownOf($open))->toEqual(RiskBreakdown::open());
});

it('scores nothing for a campaign that applies only the Guía I', function (): void {
    $trauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $trauma->update(['status' => 'closed', 'closed_at' => now()]);

    expect(breakdownOf($trauma))->toEqual(RiskBreakdown::notScored());
});

it('narrows to an area that is shown, applying the minimum after the filter', function (): void {
    anonymousGuideTwo($this->closed, $this->production, 6);
    anonymousGuideTwo($this->closed, $this->sales, 5, 'always');

    $production = breakdownOf($this->closed, $this->production->id);
    $sales = breakdownOf($this->closed, $this->sales->id);

    expect($production->answered)->toBe(6)
        ->and($production->areaLabel)->toBe('Producción')
        ->and($sales->answered)->toBe(5)
        ->and($sales->areaLabel)->toBe('Ventas')
        ->and($sales->final)->not->toBe($production->final);
});

it('does not narrow to an area that stays hidden: the answer is the center, and says no area was applied', function (): void {
    anonymousGuideTwo($this->closed, $this->production, 6);
    anonymousGuideTwo($this->closed, $this->sales, 3);

    $risk = breakdownOf($this->closed, $this->sales->id);

    expect($risk->answered)->toBe(9)
        ->and($risk->areaLabel)->toBeNull();
});

it('lists the areas shown with complementary suppression, and counts the hidden ones', function (): void {
    $third = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Logística']);
    anonymousGuideTwo($this->closed, $this->production, 6);
    anonymousGuideTwo($this->closed, $this->sales, 5);
    anonymousGuideTwo($this->closed, $third, 3);

    $risk = breakdownOf($this->closed);

    expect(array_map(fn ($area): string => $area->name, $risk->areas))->toBe(['Producción'])
        ->and($risk->hiddenAreas)->toBe(2);
});

it('carries no answer of any person: only counts, labels and names', function (): void {
    anonymousGuideTwo($this->closed, $this->production, 6);

    $json = json_encode(breakdownOf($this->closed), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('answers')->not->toContain('participant')->not->toContain('employee');
});
