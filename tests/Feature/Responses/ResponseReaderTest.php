<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Organization\Models\Area;
use App\Modules\Responses\Contracts\ResponseReader;
use App\Modules\Responses\Data\AnonymousRecord;
use App\Modules\Responses\Data\ScreeningRecord;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Responses\Support\DatabaseResponseReader;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(DatabaseResponseReader::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->campaign = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
});

it('reads the anonymous answers of one guide of a campaign, with their area and nothing else', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $answers = nom035Answers('nom035.guide-ii', 'always', clients: false, supervises: false);
    AnonymousResponse::factory()->create(['campaign_id' => $this->campaign->id, 'area_id' => $area->id, 'answers' => $answers]);
    AnonymousResponse::factory()->create(['campaign_id' => $this->campaign->id, 'instrument' => 'nom035.guide-iii', 'answers' => nom035Answers('nom035.guide-iii')]);
    AnonymousResponse::factory()->create(['campaign_id' => Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id])->id]);
    workIn($this->tenant);

    expect(resolve(ResponseReader::class)->anonymous($this->campaign->id, 'nom035.guide-ii'))
        ->toEqual([new AnonymousRecord($area->id, 'dof-2018-10-23', $answers)]);
});

it('reads no anonymous answers of another company', function (): void {
    AnonymousResponse::factory()->create(['campaign_id' => $this->campaign->id]);
    workIn(Tenant::factory()->create());

    expect(resolve(ResponseReader::class)->anonymous($this->campaign->id, 'nom035.guide-ii'))->toBe([]);
});

it('counts the Guía I answered in a campaign and those that require a clinical assessment', function (): void {
    TraumaScreening::factory()->count(3)->create(['campaign_id' => $this->campaign->id]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true]);
    TraumaScreening::factory()->create(['requires_clinical_assessment' => true]);
    workIn($this->tenant);

    expect(resolve(ResponseReader::class)->screeningCounts($this->campaign->id))->toBe(['answered' => 5, 'requiring' => 2]);
});

it('counts who requires a clinical assessment in several campaigns at once, leaving out those with nobody', function (): void {
    $other = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $nobody = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    TraumaScreening::factory()->count(2)->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true]);
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id]);
    TraumaScreening::factory()->create(['campaign_id' => $other->id, 'requires_clinical_assessment' => true]);
    TraumaScreening::factory()->create(['campaign_id' => $nobody->id]);
    TraumaScreening::factory()->create(['requires_clinical_assessment' => true]);
    workIn($this->tenant);

    $counts = resolve(ResponseReader::class)->requiringCounts([$this->campaign->id, $other->id, $nobody->id]);

    expect($counts)->toHaveCount(2)
        ->and($counts[$this->campaign->id])->toBe(2)
        ->and($counts[$other->id])->toBe(1)
        ->and(resolve(ResponseReader::class)->requiringCounts([]))->toBe([]);
});

it('pages who requires a clinical assessment, by when they answered', function (): void {
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true, 'answered_at' => '2026-11-05 15:00:00']);
    $earlier = TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id, 'requires_clinical_assessment' => true, 'answered_at' => '2026-11-03 15:00:00']);
    TraumaScreening::factory()->create(['campaign_id' => $this->campaign->id]);
    workIn($this->tenant);

    $page = resolve(ResponseReader::class)->requiringAssessment($this->campaign->id, perPage: 1, pageName: 'people');

    expect($page->total())->toBe(2)
        ->and($page->getPageName())->toBe('people')
        ->and($page->items())->toEqual([new ScreeningRecord($earlier->employee_id, CarbonImmutable::parse('2026-11-03 15:00:00'))]);
});
