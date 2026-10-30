<?php

declare(strict_types=1);

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\AnonymousResponse;
use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Results\Models\ReportDetails;
use App\Modules\Results\Support\CompanyEvaluationResults;
use App\Modules\Tenancy\Models\Tenant;

mutates(CompanyEvaluationResults::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

/** The campaign as another module reads it, inside its company. */
function recordOf(Campaign $campaign): CampaignRecord
{
    workIn(tenantOf($campaign->tenant_id));

    return resolve(CampaignRecords::class)->find($campaign->id) ?? throw new RuntimeException('Campaign not found.');
}

/** `$count` anonymous Guía II questionnaires of `$campaign`, all answered with `$value`. */
function guideTwoAnswers(Campaign $campaign, int $count, string $value): void
{
    AnonymousResponse::factory()->count($count)->create(['campaign_id' => $campaign->id, 'instrument' => 'nom035.guide-ii', 'answers' => nom035Answers('nom035.guide-ii', $value)]);
}

it('tells which closed evaluations still lack the details of their 7.7 report', function (): void {
    $filled = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    $missing = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    ReportDetails::factory()->create(['campaign_id' => $filled->id]);
    workIn($this->tenant);

    $results = resolve(EvaluationResults::class);

    expect($results->withoutReportDetails([$filled->id, $missing->id]))->toBe([$missing->id])
        ->and($results->withoutReportDetails([]))->toBe([]);
});

it('counts the final score of a closed evaluation by risk level, through the minimum group', function (): void {
    $campaign = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
    guideTwoAnswers($campaign, 3, 'always');
    guideTwoAnswers($campaign, 2, 'never');

    $levels = resolve(EvaluationResults::class)->finalLevels(recordOf($campaign));

    expect($levels)->toHaveCount(5)
        ->and(array_sum($levels ?? []))->toBe(5)
        ->and(count(array_filter($levels ?? [])))->toBe(2);
});

it('gives no final score under the minimum group, before closing, or without Guía II or III', function (Closure $campaign): void {
    expect(resolve(EvaluationResults::class)->finalLevels(recordOf($campaign($this->tenant))))->toBeNull();
})->with([
    'fewer than five' => [function (Tenant $tenant): Campaign {
        $campaign = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id]);
        guideTwoAnswers($campaign, 4, 'always');

        return $campaign;
    }],
    'still in course' => [function (Tenant $tenant): Campaign {
        $campaign = Campaign::factory()->active()->create(['tenant_id' => $tenant->id]);
        guideTwoAnswers($campaign, 5, 'always');

        return $campaign;
    }],
    'only Guía I' => [fn (Tenant $tenant): Campaign => Campaign::factory()->trauma()->create(['tenant_id' => $tenant->id, 'status' => 'closed', 'closed_at' => now()])],
]);
