<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Campaigns\Actions\CloseCampaign;
use App\Modules\Campaigns\Actions\CloseExpiredCampaigns;
use App\Modules\Campaigns\Actions\ExtendCampaign;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

mutates(ExtendCampaign::class, CloseCampaign::class, CloseExpiredCampaigns::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 17:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->campaign = Campaign::factory()->active('2026-11-14')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);
});

function answeredGuides(Campaign $campaign, int $answered, int $pending): void
{
    foreach (range(1, $answered + $pending) as $index) {
        ParticipantInstrument::factory()->create([
            'participant_id' => Participant::factory()->create(['campaign_id' => $campaign->id])->id,
            'instrument' => 'nom035.guide-ii',
            ...($index <= $answered ? ['status' => InstrumentStatus::Completed, 'completed_at' => now()] : []),
        ]);
    }
}

it('extends the last day of an active evaluation', function (): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.extend', [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-21'])
        ->assertSessionHasNoErrors();

    expect($this->campaign->refresh()->closes_on?->toDateString())->toBe('2026-11-21');
});

it('never moves the last day earlier, nor to today or before', function (string $closesOn, string $message): void {
    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.extend', [$this->tenant, $this->campaign->id]), ['closes_on' => $closesOn])
        ->assertSessionHasErrors(['closes_on' => $message]);

    expect($this->campaign->refresh()->closes_on?->toDateString())->toBe('2026-11-14');
})->with([
    'the same day' => ['2026-11-14', 'La nueva fecha debe ser posterior al 14 nov 2026.'],
    'earlier' => ['2026-11-12', 'La nueva fecha debe ser posterior al 14 nov 2026.'],
    'today' => ['2026-11-10', 'La fecha de cierre debe ser a partir de mañana.'],
]);

it('does not reopen an evaluation past its last day, even before the hourly close has run', function (): void {
    Carbon::setTestNow('2026-11-15 12:00:00');

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.extend', [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-18'])
        ->assertSessionHasErrors(['closes_on' => 'Su último día ya pasó: ya no se puede extender.']);

    expect($this->campaign->refresh()->closes_on?->toDateString())->toBe('2026-11-14');
});

it('closes an evaluation: the links stop and the audit trail records how many answered', function (): void {
    answeredGuides($this->campaign, 3, 2);
    Participant::query()->withoutGlobalScopes()->limit(1)->update(['token_hash' => Participant::hashToken('a-link')]);

    $this->actingAs($this->hr)
        ->post(route('tenant.campaigns.close', [$this->tenant, $this->campaign->id]))
        ->assertRedirect(route('tenant.campaigns.show', [$this->tenant, $this->campaign->id]));

    $entry = AuditEntry::query()->where('event', 'campaign.closed')->sole();

    expect($this->campaign->refresh()->status)->toBe(CampaignStatus::Closed)
        ->and($this->campaign->closed_at?->toIso8601String())->toBe('2026-11-10T17:00:00+00:00')
        ->and($entry->subject_id)->toBe($this->campaign->id)
        ->and($entry->actor_id)->toBe($this->hr->id)
        ->and($entry->metadata)->toEqual(['completed' => ['nom035.guide-ii' => 3], 'pending' => ['nom035.guide-ii' => 2], 'automatic' => false]);

    $this->get(route('survey.show', 'a-link'))->assertInertia(fn ($page) => $page->where('unavailable', 'closed'));
});

it('closes, extends and acts only on an active evaluation, for those who manage campaigns', function (string $route): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route($route, [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-21'])->assertForbidden();
    $this->actingAs($this->hr)->post(route($route, [$this->tenant, Campaign::factory()->active()->create()->id]), ['closes_on' => '2026-11-21'])->assertNotFound();
    $this->campaign->update(['status' => CampaignStatus::Closed, 'closed_at' => now()]);
    $this->actingAs($this->hr)->post(route($route, [$this->tenant, $this->campaign->id]), ['closes_on' => '2026-11-21'])->assertStatus(409);
})->with(['tenant.campaigns.close', 'tenant.campaigns.extend']);

it('closes the evaluations of every company whose last day ended in Mexico City', function (): void {
    $other = Campaign::factory()->active('2026-11-09')->create();
    $lastDayToday = Campaign::factory()->active('2026-11-10')->create();
    $this->campaign->update(['closes_on' => '2026-11-09']);
    answeredGuides($this->campaign, 1, 0);

    // 05:59 UTC on the 10th is still the 9th in Mexico City; at 06:00 the 9th is over.
    Carbon::setTestNow('2026-11-10 05:59:00');
    expect(resolve(CloseExpiredCampaigns::class)->handle())->toBe(0);

    Carbon::setTestNow('2026-11-10 06:00:00');
    expect(resolve(CloseExpiredCampaigns::class)->handle())->toBe(2)
        ->and($this->campaign->refresh()->status)->toBe(CampaignStatus::Closed)
        ->and($other->refresh()->status)->toBe(CampaignStatus::Closed)
        ->and($lastDayToday->refresh()->status)->toBe(CampaignStatus::Active)
        ->and(AuditEntry::query()->where('event', 'campaign.closed')->where('subject_id', $this->campaign->id)->sole()->only(['tenant_id', 'actor_id', 'metadata']))
        ->toEqual(['tenant_id' => $this->tenant->id, 'actor_id' => null, 'metadata' => ['completed' => ['nom035.guide-ii' => 1], 'pending' => [], 'automatic' => true]]);
});

it('runs the closing of expired evaluations every hour', function (): void {
    $events = collect(resolve(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'campaigns:close-expired'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *')
        ->and($events->first()->onOneServer)->toBeTrue()
        ->and($events->first()->withoutOverlapping)->toBeTrue();

    $this->artisan('campaigns:close-expired')->assertSuccessful();
});

it('closes nothing twice when another request closes it between the check and the lock', function (): void {
    $raced = false;

    Illuminate\Support\Facades\Event::listen('eloquent.retrieved: '.Campaign::class, function () use (&$raced): void {
        if (! $raced) {
            $raced = true;
            Illuminate\Support\Facades\DB::table('campaigns')->where('id', $this->campaign->id)->update(['status' => 'closed', 'closed_at' => now()]);
        }
    });

    $this->actingAs($this->hr)->post(route('tenant.campaigns.close', [$this->tenant, $this->campaign->id]))->assertStatus(409);

    expect(AuditEntry::query()->where('event', 'campaign.closed')->count())->toBe(0);
});

it('keeps closing the other companies\' evaluations when one fails', function (): void {
    $broken = Campaign::factory()->active('2026-11-01')->create();
    $fine = Campaign::factory()->active('2026-11-01')->create();
    $failed = false;

    // The first one to close fails (say, the database hiccups); the next must still close.
    Illuminate\Support\Facades\Event::listen('eloquent.updating: '.Campaign::class, function (Campaign $campaign) use ($broken, &$failed): void {
        if ($campaign->id === $broken->id && ! $failed) {
            $failed = true;

            throw new RuntimeException('Database hiccup');
        }
    });

    expect(resolve(CloseExpiredCampaigns::class)->handle())->toBe(1)
        ->and($fine->refresh()->status)->toBe(CampaignStatus::Closed)
        ->and($broken->refresh()->status)->toBe(CampaignStatus::Active);
});
