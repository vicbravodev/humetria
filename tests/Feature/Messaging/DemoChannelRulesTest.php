<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Billing\Models\UsageCounter;
use App\Modules\Billing\Models\UsageEvent;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\Participant;
use App\Modules\Campaigns\Notifications\SurveyInvitation;
use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Support\ChannelRules;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

mutates(ChannelRules::class);

/*
 * Decision 5 of the guided demo: in the demo company the invitation goes out by every channel that works (mail and WhatsApp) to the
 * real people added live; a real company keeps writing through the first channel only.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-02 17:00:00');
    Http::preventStrayRequests();
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
});

/** A company as the demo session leaves it: mail first, WhatsApp on with the words in force, two people with an email and a mobile. */
function demoChannelsCompany(bool $demo = true, bool $whatsApp = true): array
{
    $tenant = $demo ? Tenant::factory()->demo()->create() : Tenant::factory()->create();
    $hr = memberOf($tenant, TenantRole::Hr);
    $plant = WorkCenter::factory()->for($tenant)->create();
    TenantChannelFactory::new()->for($tenant)->create(['channel' => 'mail', 'preference' => 1]);
    TenantChannelFactory::new()->for($tenant)->whatsAppEnabled()->create(['preference' => 2, 'enabled' => $whatsApp]);
    $staff = staffOf($tenant, $plant, 2)->values();
    $staff->each(fn ($employee, int $i) => $employee->update(['mobile' => '+52818687'.(5700 + $i), 'email' => "publico{$i}@example.test"]));

    return [$tenant, $hr, $plant, $staff];
}

function demoLaunch(Tenant $tenant, User $hr, WorkCenter $plant): Campaign
{
    test()->actingAs($hr)->post(route('tenant.campaigns.store', $tenant), ['work_center_id' => $plant->id]);
    $campaign = Campaign::query()->withoutGlobalScopes()->latest()->firstOrFail();
    test()->actingAs($hr)->post(route('tenant.campaigns.launch', [$tenant, $campaign->id]), ['closes_on' => '2026-11-20'])->assertSessionHasNoErrors();

    return $campaign;
}

/** @return array<string, list<string>> the channels of each person's invitation, by their email */
function demoChannelsSent(): array
{
    $sent = [];
    Notification::assertSentOnDemand(SurveyInvitation::class, function (SurveyInvitation $mail, array $channels, AnonymousNotifiable $notifiable) use (&$sent): bool {
        $sent[(string) ($notifiable->routes['mail'] ?? $notifiable->routes['whatsapp'])] = $channels;

        return true;
    });
    ksort($sent);

    return $sent;
}

/** @return Collection<int, string|null> */
function demoInvitedVia(Campaign $campaign): Collection
{
    return Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('invited_via');
}

it('sends a demo survey invitation by mail and WhatsApp', function (): void {
    Notification::fake();
    [$tenant, $hr, $plant] = demoChannelsCompany();

    $campaign = demoLaunch($tenant, $hr, $plant);

    expect(demoChannelsSent())->toBe(['publico0@example.test' => ['mail', 'whatsapp'], 'publico1@example.test' => ['mail', 'whatsapp']])
        // One column, the first channel: the mail carried the link too, so «por correo» stays true.
        ->and(demoInvitedVia($campaign)->unique()->values()->all())->toBe(['mail']);
});

it('still sends by one channel in a real company', function (): void {
    Notification::fake();
    [$tenant, $hr, $plant] = demoChannelsCompany(demo: false);

    demoLaunch($tenant, $hr, $plant);

    expect(demoChannelsSent())->toBe(['publico0@example.test' => ['mail'], 'publico1@example.test' => ['mail']]);
});

it('falls back to mail in the demo when the number opted out', function (): void {
    Notification::fake();
    [$tenant, $hr, $plant] = demoChannelsCompany();
    ['hash' => $hash, 'version' => $version] = (new RecipientHash)->current('+528186875700');
    OptOutFactory::new()->create(['tenant_id' => $tenant->id, 'recipient_hash' => $hash, 'key_version' => $version]);

    demoLaunch($tenant, $hr, $plant);

    expect(demoChannelsSent())->toBe(['publico0@example.test' => ['mail'], 'publico1@example.test' => ['mail', 'whatsapp']]);
});

it('keeps WhatsApp closed in the demo while its channel is off', function (): void {
    Notification::fake();
    [$tenant, $hr, $plant] = demoChannelsCompany(whatsApp: false);

    demoLaunch($tenant, $hr, $plant);

    expect(demoChannelsSent())->toBe(['publico0@example.test' => ['mail'], 'publico1@example.test' => ['mail']]);
});

it('lets the demo send mail and WhatsApp without a billed plan and without counting them', function (): void {
    $gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $gateway);
    [$tenant, $hr, $plant, $staff] = demoChannelsCompany();
    // Someone of the audience who gave only their number: WhatsApp is the only way to reach them.
    $staff[1]->update(['email' => null]);

    $campaign = demoLaunch($tenant, $hr, $plant);

    expect(Participant::query()->withoutGlobalScopes()->where('campaign_id', $campaign->id)->pluck('invitation_status')->map->value->all())->toBe(['sent', 'sent'])
        ->and($gateway->sent())->toHaveCount(2)
        ->and(UsageEvent::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(UsageCounter::query()->where('tenant_id', $tenant->id)->sum('reserved'))->toEqual(0)
        ->and(UsageCounter::query()->where('tenant_id', $tenant->id)->sum('used'))->toEqual(0);
});
