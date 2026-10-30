<?php

declare(strict_types=1);

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Campaigns\Data\ComplianceCycle;
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
        closesOn: '19 nov 2026',
        daysLeft: null,
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
        ->closesOn->toBe('4 dic 2026')
        // Its last day, 4 dic, counted from today in Mexico City (20 nov).
        ->daysLeft->toBe(14)
        ->invited->toBe(['nom035.guide-i' => 0, 'nom035.guide-ii' => 0])
        ->answered->toBe(['nom035.guide-i' => 0, 'nom035.guide-ii' => 0])
        ->and($records->find($draft->id))
        ->closed->toBeFalse()
        ->launchedOn->toBeNull()
        ->closesOn->toBeNull()
        ->daysLeft->toBeNull();
});

it('finds no campaign of another company, nor one that does not exist', function (): void {
    $other = Campaign::factory()->closed()->create();
    workIn($this->tenant);

    expect(resolve(CampaignRecords::class)->find($other->id))->toBeNull()
        ->and(resolve(CampaignRecords::class)->find('00000000-0000-4000-8000-000000000000'))->toBeNull()
        ->and(resolve(CampaignRecords::class)->find('not-a-uuid'))->toBeNull();
});

it('lists the campaigns in course by deadline, then the drafts and those closed in the last 30 days', function (): void {
    $later = Campaign::factory()->active('2026-12-10')->create(['tenant_id' => $this->tenant->id]);
    $sooner = Campaign::factory()->active('2026-11-25')->create(['tenant_id' => $this->tenant->id]);
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id]);
    $recent = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2026-10-22 12:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2026-10-20 12:00:00']); // 31 days ago.
    Campaign::factory()->active()->create(); // Another company's.
    workIn($this->tenant);

    expect(array_map(fn (CampaignRecord $record): string => $record->id, resolve(CampaignRecords::class)->current()))
        ->toBe([$sooner->id, $later->id, $draft->id, $recent->id]);
});

it('pages the closed NOM-035 evaluations, the latest first, leaving out Guía I by event', function (): void {
    $older = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2025-03-01 12:00:00']);
    $latest = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2026-11-01 12:00:00']);
    Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id, 'status' => 'closed', 'closed_at' => '2026-11-02 12:00:00']);
    Campaign::factory()->active()->create(['tenant_id' => $this->tenant->id]);
    Campaign::factory()->closed()->create();
    workIn($this->tenant);

    $page = resolve(CampaignRecords::class)->closedEvaluations(1, 'closed');

    expect($page->total())->toBe(2)
        ->and($page->getPageName())->toBe('closed')
        ->and($page->items()[0]->id)->toBe($latest->id)
        ->and(resolve(CampaignRecords::class)->closedEvaluations(5)->items()[1]->id)->toBe($older->id);
});

it('tells where each work center stands in its two-year cycle', function (): void {
    $running = Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $this->tenant->id, 'launched_at' => '2026-11-10 18:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $running->work_center_id, 'closed_at' => '2024-06-01 18:00:00']);
    $current = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2025-11-20 18:00:00']);
    Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $current->work_center_id, 'closed_at' => '2023-01-10 18:00:00']);
    // A draft does not change where it stands.
    Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $current->work_center_id]);
    $overdue = Campaign::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'closed_at' => '2024-11-19 18:00:00']);
    // Guía I by event is not the periodic evaluation.
    $onlyTrauma = Campaign::factory()->trauma()->create(['tenant_id' => $this->tenant->id]);
    workIn($this->tenant);

    $never = '00000000-0000-4000-8000-000000000000';
    $cycles = resolve(CampaignRecords::class)->cycles([$running->work_center_id, $current->work_center_id, $overdue->work_center_id, $onlyTrauma->work_center_id, $never]);

    expect($cycles)->toEqual([
        // 10 of its 20 days gone by.
        $running->work_center_id => new ComplianceCycle('in_course', '10 nov 2026', '30 nov 2026', 50),
        // One year of two.
        $current->work_center_id => new ComplianceCycle('up_to_date', '20 nov 2025', '20 nov 2027', 50),
        $overdue->work_center_id => new ComplianceCycle('overdue', '19 nov 2024', '19 nov 2026', 100),
        $onlyTrauma->work_center_id => new ComplianceCycle('never', null, null, 0),
        $never => new ComplianceCycle('never', null, null, 0),
    ]);
});
