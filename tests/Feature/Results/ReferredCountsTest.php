<?php

declare(strict_types=1);

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Responses\Models\TraumaScreening;
use App\Modules\Results\Contracts\EvaluationResults;
use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Models\ScreeningReferral;
use App\Modules\Results\Support\CompanyEvaluationResults;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

mutates(CompanyEvaluationResults::class);

/** A person of the evaluation whose Guía I requires a clinical assessment, channeled as given. */
function channeled(Campaign $campaign, ReferralStatus $status = ReferralStatus::Referred): void
{
    $screening = TraumaScreening::factory()->create(['campaign_id' => $campaign->id, 'requires_clinical_assessment' => true]);
    ScreeningReferral::factory()->create(['tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'employee_id' => $screening->employee_id, 'status' => $status]);
}

it('counts by evaluation the people already channeled or attended, in one read', function (): void {
    $tenant = Tenant::factory()->create();
    workIn($tenant);
    $first = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id]);
    $other = Campaign::factory()->closed()->create(['tenant_id' => $tenant->id]);
    channeled($first);
    channeled($first, ReferralStatus::Attended);
    channeled($other);

    expect(app(EvaluationResults::class)->channeledCounts([$first->id, $other->id, (string) Str::uuid()]))->toBe([
        $first->id => 2,
        $other->id => 1,
    ])->and(app(EvaluationResults::class)->channeledCounts([]))->toBe([]);
});
