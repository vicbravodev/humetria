<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\ChannelConsent;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Support\ChannelRules;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Messaging\Support\WhatsAppSwitch;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Support\Facades\Log;

mutates(ChannelRules::class, WhatsAppSwitch::class);

const CONSENT_PHONE = '+528186875767';

beforeEach(function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    $this->tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();
});

function canSend(Tenant $tenant, string $channel = 'whatsapp', string $phone = CONSENT_PHONE): bool
{
    app()->forgetScopedInstances();

    return app(ChannelConsent::class)->canSend($tenant->id, $channel, $phone);
}

function optOutOf(string $phone, ?string $tenantId): void
{
    ['hash' => $hash, 'version' => $version] = (new RecipientHash)->current($phone);
    OptOutFactory::new()->create(['tenant_id' => $tenantId, 'recipient_hash' => $hash, 'key_version' => $version]);
}

it('sends when the company activated WhatsApp with the current words and the phone has no opt-out', function (): void {
    expect(canSend($this->tenant))->toBeTrue();
});

it('does not send without an activation', function (): void {
    expect(canSend(Tenant::factory()->create()))->toBeFalse();
});

it('does not send when the accepted words are not the current version', function (): void {
    config(['whatsapp.consent.version' => '2027-01-01']);

    expect(canSend($this->tenant))->toBeFalse();
});

it('does not send when the company turned WhatsApp off', function (): void {
    $other = Tenant::factory()->create();
    TenantChannelFactory::new()->for($other)->whatsAppEnabled()->create(['enabled' => false]);

    expect(canSend($other))->toBeFalse();
});

// Decision 5 of the guided demo (2026-11-26): the demo writes through WhatsApp under the same rules as any company.
it('sends from the demo company under the same rules', function (): void {
    becomeDemo($this->tenant);

    expect(canSend($this->tenant))->toBeTrue();

    optOutOf(CONSENT_PHONE, $this->tenant->id);

    expect(canSend($this->tenant))->toBeFalse();
});

it('does not send a phone it cannot read', function (): void {
    expect(canSend($this->tenant, 'whatsapp', 'not a phone'))->toBeFalse();
});

it('does not send while the function is off, paused by the operator or paused by an open pause', function (string $case): void {
    match ($case) {
        'off' => config(['whatsapp.enabled' => false]),
        'paused' => config(['whatsapp.paused' => true]),
        'open pause' => Pause::query()->create(['reason' => 'failure_rate', 'started_at' => now()]),
    };

    expect(canSend($this->tenant))->toBeFalse();
})->with(['off', 'paused', 'open pause']);

it('sends again once the pause is closed', function (): void {
    Pause::query()->create(['reason' => 'platform_cap', 'started_at' => now()->subHour(), 'ended_at' => now()]);

    expect(canSend($this->tenant))->toBeTrue();
});

it('lets an opt-out of company A stop A and not B', function (): void {
    $b = Tenant::factory()->create();
    TenantChannelFactory::new()->for($b)->whatsAppEnabled()->create();
    optOutOf(CONSENT_PHONE, $this->tenant->id);

    expect(canSend($this->tenant))->toBeFalse()
        ->and(canSend($b))->toBeTrue();
});

it('lets a global opt-out stop every company', function (): void {
    $b = Tenant::factory()->create();
    TenantChannelFactory::new()->for($b)->whatsAppEnabled()->create();
    optOutOf(CONSENT_PHONE, null);

    expect(canSend($this->tenant))->toBeFalse()
        ->and(canSend($b))->toBeFalse();
});

it('finds an opt-out written under an older key', function (): void {
    optOutOf(CONSENT_PHONE, $this->tenant->id);
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key'), 2 => base64_encode('newer')]]);

    expect(canSend($this->tenant))->toBeFalse();
});

it('does not erase the opt-out when the owner activates again', function (): void {
    optOutOf(CONSENT_PHONE, $this->tenant->id);
    $this->tenant->refresh();
    DB::table('tenant_channels')->where('tenant_id', $this->tenant->id)->update(['enabled_at' => now()->addDay()]);

    expect(canSend($this->tenant))->toBeFalse();
});

it('does not govern mail with the WhatsApp rules: mail is sendable while its channel is on', function (): void {
    expect(canSend($this->tenant, 'mail'))->toBeTrue();

    TenantChannelFactory::new()->for($b = Tenant::factory()->create())->mailDisabled()->create();

    expect(canSend($b, 'mail'))->toBeFalse();
});

it('pauses once, with the reason, and logs it as an error', function (): void {
    Log::spy();

    app(WhatsAppSwitch::class)->pause('failure_rate');
    app(WhatsAppSwitch::class)->pause('platform_cap');

    expect(Pause::query()->whereNull('ended_at')->count())->toBe(1)
        ->and(Pause::query()->firstOrFail()->reason)->toBe('failure_rate')
        ->and(app(WhatsAppSwitch::class)->available())->toBeFalse();
    Log::shouldHaveReceived('error')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.paused_automatically' && $context === ['reason' => 'failure_rate'])->once();
});

it('refuses a reason the table does not know', function (): void {
    app(WhatsAppSwitch::class)->pause('because');
})->throws(Illuminate\Database\QueryException::class);
