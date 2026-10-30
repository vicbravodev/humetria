<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('stores an evaluation draft of a work center', function (): void {
    $campaign = Campaign::factory()->create();
    workIn(tenantOf($campaign->tenant_id));

    $read = Campaign::query()->sole();

    expect($read->kind)->toBe(CampaignKind::Nom035Evaluation)
        ->and($read->status)->toBe(CampaignStatus::Draft)
        ->and($read->population)->toBe(Population::All)
        ->and($read->instruments)->toBe(['nom035.guide-i', 'nom035.guide-ii'])
        ->and($read->closes_on)->toBeNull();
});

it('ties a campaign to a work center of its own company only', function (): void {
    $other = WorkCenter::factory()->create();

    Campaign::factory()->create(['work_center_id' => $other->id]);
})->throws(QueryException::class);

it('allows one open NOM-035 evaluation per work center', function (): void {
    $first = Campaign::factory()->create();

    Campaign::factory()->create(['tenant_id' => $first->tenant_id, 'work_center_id' => $first->work_center_id]);
})->throws(UniqueConstraintViolationException::class);

it('opens a new evaluation once the previous one closed, and any number of Guía I campaigns', function (): void {
    $closed = Campaign::factory()->closed()->create();
    $attributes = ['tenant_id' => $closed->tenant_id, 'work_center_id' => $closed->work_center_id];

    Campaign::factory()->active()->create($attributes);
    Campaign::factory()->trauma()->create($attributes);
    Campaign::factory()->trauma()->create($attributes);

    expect(Campaign::query()->withoutGlobalScopes()->count())->toBe(4);
});

it('never stores an active campaign without its deadline', function (): void {
    Campaign::factory()->create(['status' => CampaignStatus::Active, 'launched_at' => now(), 'closes_on' => null]);
})->throws(QueryException::class);

it('lists a person once per campaign, with each guide they were given', function (): void {
    $participant = Participant::factory()->create();
    ParticipantInstrument::factory()->for($participant)->create(['instrument' => 'nom035.guide-i']);
    ParticipantInstrument::factory()->for($participant)->create(['instrument' => 'nom035.guide-ii']);
    workIn(tenantOf($participant->tenant_id));

    expect(Participant::query()->sole()->instruments->pluck('status', 'instrument')->all())->toBe([
        'nom035.guide-i' => InstrumentStatus::Pending,
        'nom035.guide-ii' => InstrumentStatus::Pending,
    ]);

    Participant::factory()->create(['tenant_id' => $participant->tenant_id, 'campaign_id' => $participant->campaign_id, 'employee_id' => $participant->employee_id]);
})->throws(UniqueConstraintViolationException::class);

it('never lists a person of another company in a campaign', function (): void {
    $campaign = Campaign::factory()->create();

    Participant::factory()->create(['tenant_id' => $campaign->tenant_id, 'campaign_id' => $campaign->id, 'employee_id' => Employee::factory()->create()->id]);
})->throws(QueryException::class);

it('marks a guide answered only with the moment it was', function (): void {
    ParticipantInstrument::factory()->create(['status' => InstrumentStatus::Completed, 'completed_at' => null]);
})->throws(QueryException::class);

it('never shows another company\'s campaigns, people or guides', function (): void {
    ParticipantInstrument::factory()->create();
    workIn(Tenant::factory()->create());

    expect(Campaign::query()->count())->toBe(0)
        ->and(Participant::query()->count())->toBe(0)
        ->and(ParticipantInstrument::query()->count())->toBe(0);
});
