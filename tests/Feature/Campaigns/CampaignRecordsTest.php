<?php

declare(strict_types=1);

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\CompanyCampaignRecords;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;

mutates(CompanyCampaignRecords::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
});

/** Gives `$instrument` to one more person of the campaign, in `$status`. */
function giveGuide(Campaign $campaign, string $instrument, InstrumentStatus $status): void
{
    ParticipantInstrument::factory()->create([
        'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
        'instrument' => $instrument,
        'status' => $status,
        'completed_at' => $status === InstrumentStatus::Completed ? now() : null,
        'cancelled_at' => $status === InstrumentStatus::Cancelled ? now() : null,
    ]);
}

it('describes a campaign of the company with who answered each guide, counted', function (): void {
    $campaign = Campaign::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'instruments' => ['nom035.guide-i', 'nom035.guide-iii'],
        'workers' => 80,
        'population' => Population::Sample,
        'sample_size' => 67,
        'quotas' => ['female' => 30, 'male' => 37],
        'launched_at' => '2026-11-02 16:00:00',
        'closed_at' => '2026-11-20 05:30:00',
    ]);
    giveGuide($campaign, 'nom035.guide-iii', InstrumentStatus::Completed);
    giveGuide($campaign, 'nom035.guide-iii', InstrumentStatus::Completed);
    giveGuide($campaign, 'nom035.guide-iii', InstrumentStatus::Pending);
    giveGuide($campaign, 'nom035.guide-iii', InstrumentStatus::Cancelled);
    giveGuide($campaign, 'nom035.guide-i', InstrumentStatus::Completed);
    workIn($this->tenant);

    expect(resolve(CampaignRecords::class)->find($campaign->id))->toEqual(new CampaignRecord(
        id: $campaign->id,
        workCenterId: $campaign->work_center_id,
        kind: 'nom035_evaluation',
        kindLabel: 'Evaluación NOM-035',
        closed: true,
        instruments: ['nom035.guide-i', 'nom035.guide-iii'],
        workers: 80,
        sample: true,
        sampleSize: 67,
        launchedOn: '2 nov 2026',
        // 23:30 of the 19th in Mexico City.
        closedOn: '19 nov 2026',
        invited: ['nom035.guide-i' => 1, 'nom035.guide-iii' => 3],
        answered: ['nom035.guide-i' => 1, 'nom035.guide-iii' => 2],
    ));
});

it('describes a campaign still open, or a draft, as not closed', function (): void {
    $active = Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);

    $records = resolve(CampaignRecords::class);

    expect($records->find($active->id))
        ->closed->toBeFalse()
        ->sample->toBeFalse()
        ->closedOn->toBeNull()
        ->launchedOn->toBe('20 nov 2026')
        ->invited->toBe(['nom035.guide-i' => 0, 'nom035.guide-ii' => 0])
        ->answered->toBe(['nom035.guide-i' => 0, 'nom035.guide-ii' => 0])
        ->and($records->find($draft->id))
        ->closed->toBeFalse()
        ->launchedOn->toBeNull();
});

it('finds no campaign of another company, nor one that does not exist', function (): void {
    $other = Campaign::factory()->closed()->create();
    workIn($this->tenant);

    expect(resolve(CampaignRecords::class)->find($other->id))->toBeNull()
        ->and(resolve(CampaignRecords::class)->find('00000000-0000-4000-8000-000000000000'))->toBeNull()
        ->and(resolve(CampaignRecords::class)->find('not-a-uuid'))->toBeNull();
});
