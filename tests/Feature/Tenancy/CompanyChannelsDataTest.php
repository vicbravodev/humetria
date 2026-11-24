<?php

declare(strict_types=1);

use App\Modules\Tenancy\Contracts\CompanyChannels;
use App\Modules\Tenancy\Data\ChannelSetting;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantChannel;
use App\Modules\Tenancy\Support\EloquentCompanyChannels;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

mutates(EloquentCompanyChannels::class);

/** The message of the database refusing the insert, or null when it let it in; inside a savepoint, so the test goes on. */
function refusedChannel(Closure $insert): ?string
{
    try {
        DB::transaction($insert);
    } catch (QueryException $refusal) {
        return $refusal->getMessage();
    }

    return null;
}

/** @return list<array{channel: string, enabled: bool, preference: int}> */
function channelsOf(Tenant $tenant): array
{
    return array_map(
        fn (ChannelSetting $setting): array => ['channel' => $setting->channel, 'enabled' => $setting->enabled, 'preference' => $setting->preference],
        app(CompanyChannels::class)->settings($tenant->id),
    );
}

it('gives a company with no rows mail on first and WhatsApp off second', function (): void {
    $tenant = Tenant::factory()->create();

    expect(channelsOf($tenant))->toBe([
        ['channel' => 'mail', 'enabled' => true, 'preference' => 1],
        ['channel' => 'whatsapp', 'enabled' => false, 'preference' => 2],
    ])
        ->and(app(CompanyChannels::class)->monthlyCap($tenant->id, 'whatsapp'))->toBeNull();
});

it('lets a row change only its own channel', function (): void {
    $tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($tenant)->mailDisabled()->create();

    expect(channelsOf($tenant))->toBe([
        ['channel' => 'mail', 'enabled' => false, 'preference' => 1],
        ['channel' => 'whatsapp', 'enabled' => false, 'preference' => 2],
    ]);
});

it('orders by preference, then mail first', function (): void {
    $tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($tenant)->create(['channel' => 'mail', 'preference' => 2]);

    expect(array_column(channelsOf($tenant), 'channel'))->toBe(['whatsapp', 'mail']);

    $tie = Tenant::factory()->create();
    TenantChannelFactory::new()->for($tie)->whatsAppEnabled()->create(['preference' => 1]);

    expect(array_column(channelsOf($tie), 'channel'))->toBe(['mail', 'whatsapp']);
});

it('carries the consent version and the owner cap of WhatsApp', function (): void {
    $tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($tenant)->whatsAppEnabled()->create(['monthly_cap' => 300, 'consent_wording_version' => '2026-11-18']);

    $whatsApp = app(CompanyChannels::class)->settings($tenant->id)[1];

    expect($whatsApp->enabled)->toBeTrue()
        ->and($whatsApp->consentVersion)->toBe('2026-11-18')
        ->and($whatsApp->monthlyCap)->toBe(300)
        ->and(app(CompanyChannels::class)->monthlyCap($tenant->id, 'whatsapp'))->toBe(300)
        ->and(app(CompanyChannels::class)->monthlyCap($tenant->id, 'mail'))->toBeNull();
});

it('does not read the rows of another company', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    TenantChannelFactory::new()->for($b)->whatsAppEnabled()->create(['monthly_cap' => 50]);

    expect(channelsOf($a)[1]['enabled'])->toBeFalse()
        ->and(app(CompanyChannels::class)->monthlyCap($a->id, 'whatsapp'))->toBeNull()
        ->and(channelsOf($b)[1]['enabled'])->toBeTrue();
});

it('serves a company that is not the current one (jobs and webhooks)', function (): void {
    $current = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    TenantChannelFactory::new()->for($other)->whatsAppEnabled()->create();
    workIn($current);

    expect(channelsOf($other)[1]['enabled'])->toBeTrue();
});

it('scopes the model to the current company', function (): void {
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    TenantChannelFactory::new()->for($a)->whatsAppEnabled()->create();
    TenantChannelFactory::new()->for($b)->whatsAppEnabled()->create();
    workIn($a);

    expect(TenantChannel::query()->count())->toBe(1)
        ->and(TenantChannel::query()->firstOrFail()->tenant_id)->toBe($a->id);
});

it('refuses what its CHECKs and its unique forbid', function (string $label, array $override): void {
    $tenant = Tenant::factory()->create();
    $row = TenantChannelFactory::new()->for($tenant)->make($override)->getAttributes();

    expect(refusedChannel(fn () => DB::table('tenant_channels')->insert([...$row, 'id' => (string) Illuminate\Support\Str::uuid()])))->not->toBeNull($label);
})->with([
    'a channel that does not exist' => ['channel', ['channel' => 'sms']],
    'a preference of 0' => ['preference', ['preference' => 0]],
    'a preference of 3' => ['preference', ['preference' => 3]],
    'a cap on mail' => ['cap on mail', ['channel' => 'mail', 'monthly_cap' => 10]],
    'a cap of 0' => ['cap 0', ['channel' => 'whatsapp', 'monthly_cap' => 0]],
    'WhatsApp on with no consent version' => ['consent', ['channel' => 'whatsapp', 'enabled' => true, 'consent_wording_version' => null, 'enabled_at' => now()]],
    'WhatsApp on with no date' => ['date', ['channel' => 'whatsapp', 'enabled' => true, 'consent_wording_version' => 'v', 'enabled_at' => null]],
]);

it('refuses two rows of the same company and channel', function (): void {
    $tenant = Tenant::factory()->create();
    TenantChannelFactory::new()->for($tenant)->create(['channel' => 'mail']);

    expect(refusedChannel(fn () => TenantChannelFactory::new()->for($tenant)->create(['channel' => 'mail'])))->not->toBeNull();
});
