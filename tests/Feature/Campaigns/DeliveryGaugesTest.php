<?php

declare(strict_types=1);

use App\Modules\Campaigns\Enums\InstrumentStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Models\ParticipantInstrument;
use App\Modules\Campaigns\Support\ChannelAnswers;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Support\ChannelDeliveryTallies;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Tenancy\TenantChannelFactory;

mutates(ChannelDeliveryTallies::class, ChannelAnswers::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->campaign = Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();
});

/** `$count` messages of the channel for the campaign, of which `$undelivered` did not arrive. */
function messages(Tenant $tenant, Campaign $campaign, string $channel, int $count, int $undelivered = 0): void
{
    foreach (range(1, $count) as $index) {
        $delivery = DeliveryFactory::new()->state(['tenant_id' => $tenant->id, 'source_type' => 'campaign', 'source_id' => $campaign->id, 'channel' => $channel, 'status' => $index <= $undelivered ? DeliveryStatus::Failed : DeliveryStatus::Delivered]);
        ($channel === 'mail' ? $delivery->mail() : $delivery)->create();
    }
}

/** @return array<string, mixed> */
function reachOf(Tests\TestCase $test): array
{
    $reach = $test->actingAs($test->hr)->get(route('tenant.campaigns.show', [$test->tenant, $test->campaign->id]))->assertOk()->inertiaProps()['reach'];

    return is_array($reach) ? $reach : [];
}

it('shows one gauge per active channel with the total and how many did not arrive (CUJ 6)', function (): void {
    messages($this->tenant, $this->campaign, 'mail', 20, 6);
    messages($this->tenant, $this->campaign, 'whatsapp', 10, 5);

    $reach = reachOf($this);

    expect($reach['minGroup'])->toBe(5)
        ->and($reach['channels'])->toBe([
            ['channel' => 'mail', 'total' => 20, 'delivered' => 14, 'undelivered' => 6, 'undeliveredBelowMinimum' => false],
            ['channel' => 'whatsapp', 'total' => 10, 'delivered' => 5, 'undelivered' => 5, 'undeliveredBelowMinimum' => false],
        ]);
});

it('applies the minimum of 5 to the total and to the undelivered: 4, 5 and 6', function (int $total, int $undelivered, array $expected): void {
    messages($this->tenant, $this->campaign, 'whatsapp', $total, $undelivered);

    expect(reachOf($this)['channels'])->toBe([['channel' => 'whatsapp', ...$expected]]);
})->with([
    'total 4 hides everything' => [4, 4, ['total' => null, 'delivered' => null, 'undelivered' => null, 'undeliveredBelowMinimum' => false]],
    'total 5, 4 undelivered' => [5, 4, ['total' => 5, 'delivered' => null, 'undelivered' => null, 'undeliveredBelowMinimum' => true]],
    'total 5, 5 undelivered' => [5, 5, ['total' => 5, 'delivered' => 0, 'undelivered' => 5, 'undeliveredBelowMinimum' => false]],
    'total 6, 5 undelivered' => [6, 5, ['total' => 6, 'delivered' => 1, 'undelivered' => 5, 'undeliveredBelowMinimum' => false]],
    'total 10, none undelivered' => [10, 0, ['total' => 10, 'delivered' => null, 'undelivered' => null, 'undeliveredBelowMinimum' => true]],
    'total 10, 6 undelivered' => [10, 6, ['total' => 10, 'delivered' => 4, 'undelivered' => 6, 'undeliveredBelowMinimum' => false]],
]);

it('counts a reminder, a resend or a fallback as one more message, not one more person', function (): void {
    $one = DeliveryFactory::new()->create(['tenant_id' => $this->tenant->id, 'source_type' => 'campaign', 'source_id' => $this->campaign->id, 'channel' => 'whatsapp', 'status' => DeliveryStatus::Delivered]);
    foreach (range(1, 4) as $_) {
        DeliveryFactory::new()->create(['tenant_id' => $this->tenant->id, 'source_type' => 'campaign', 'source_id' => $this->campaign->id, 'channel' => 'whatsapp', 'invitation_id' => $one->invitation_id, 'status' => DeliveryStatus::Read]);
    }

    expect(reachOf($this)['channels'][0]['total'])->toBe(5);
});

it('does not show a channel the company has turned off', function (): void {
    messages($this->tenant, $this->campaign, 'whatsapp', 10, 5);
    App\Modules\Tenancy\Models\TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['enabled' => false]);

    expect(reachOf($this)['channels'])->toBe([]);
});

it('counts only the campaign and the company of the page', function (): void {
    messages($this->tenant, $this->campaign, 'whatsapp', 5, 5);
    $other = Tenant::factory()->create();
    $foreign = Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $other->id, 'work_center_id' => WorkCenter::factory()->for($other)->create()->id]);
    messages($other, $foreign, 'whatsapp', 9);
    messages($this->tenant, Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]), 'whatsapp', 7);
    DeliveryFactory::new()->create(['tenant_id' => $other->id, 'source_type' => 'campaign', 'source_id' => $this->campaign->id, 'channel' => 'whatsapp']);

    expect(reachOf($this)['channels'][0]['total'])->toBe(5);
});

it('answers 404, not 403, for the campaign of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Campaign::factory()->active('2026-11-30')->create(['tenant_id' => $other->id, 'work_center_id' => WorkCenter::factory()->for($other)->create()->id]);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.show', [$this->tenant, $foreign->id]))->assertNotFound();
});

it('has no reach before the launch', function (): void {
    $draft = Campaign::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => WorkCenter::factory()->for($this->tenant)->create()->id]);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.show', [$this->tenant, $draft->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->where('reach', null));
});

/** `$answered` people of the channel answered, `$unanswered` did not. */
function people(Campaign $campaign, string $channel, int $answered, int $unanswered): void
{
    foreach ([[true, $answered], [false, $unanswered]] as [$did, $count]) {
        for ($i = 0; $i < $count; $i++) {
            $participant = Participant::factory()->create(['campaign_id' => $campaign->id, 'invited_via' => $channel]);
            ParticipantInstrument::factory()->create($did
                ? ['participant_id' => $participant->id, 'status' => InstrumentStatus::Completed, 'completed_at' => now()]
                : ['participant_id' => $participant->id]);
        }
    }
}

it('crosses the channel with who answered only when every cell of every channel reaches 5 (rule 10): 4, 5 and 6', function (int $cell, bool $shown): void {
    people($this->campaign, 'mail', 20, 20);
    people($this->campaign, 'whatsapp', $cell, 20);

    $answers = reachOf($this)['answeredByChannel'];

    expect($answers === null)->toBe(! $shown);
    $shown && expect($answers)->toBe([
        ['channel' => 'mail', 'answered' => 20, 'unanswered' => 20],
        ['channel' => 'whatsapp', 'answered' => $cell, 'unanswered' => 20],
    ]);
})->with([[4, false], [5, true], [6, true]]);

it('hides the table when only a cell of the other channel is small too', function (): void {
    people($this->campaign, 'mail', 20, 4);
    people($this->campaign, 'whatsapp', 20, 20);

    expect(reachOf($this)['answeredByChannel'])->toBeNull();
});

it('leaves out who has no channel and counts a person once however many guides they answered', function (): void {
    workIn($this->tenant);
    people($this->campaign, 'mail', 5, 5);
    Participant::factory()->create(['campaign_id' => $this->campaign->id, 'invited_via' => null]);
    $twice = Participant::query()->where('invited_via', 'mail')->firstOrFail();
    ParticipantInstrument::factory()->create(['participant_id' => $twice->id, 'instrument' => 'nom035.guide-iii', 'status' => InstrumentStatus::Completed, 'completed_at' => now()]);

    expect(reachOf($this)['answeredByChannel'])->toBe([['channel' => 'mail', 'answered' => 5, 'unanswered' => 5]]);
});

it('never puts a name, a phone or a status in the props of the reach', function (): void {
    messages($this->tenant, $this->campaign, 'whatsapp', 6, 5);
    people($this->campaign, 'whatsapp', 6, 6);

    $json = json_encode(reachOf($this), JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('recipient')->not->toContain('provider_message')->not->toContain('occurred_at')->not->toContain('employee')
        ->and(collect(json_decode($json, true)['channels'])->every(fn (array $row): bool => array_keys($row) === ['channel', 'total', 'delivered', 'undelivered', 'undeliveredBelowMinimum']))->toBeTrue();
});
