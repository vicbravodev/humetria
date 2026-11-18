<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\UpdateCompanyChannels;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\CompanyChannelsController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantChannel;
use Database\Factories\Tenancy\TenantChannelFactory;
use Inertia\Testing\AssertableInertia as Assert;

mutates(UpdateCompanyChannels::class, CompanyChannelsController::class);

beforeEach(function (): void {
    config(['whatsapp.enabled' => true]);
    [, $account] = paidCompany();
    $this->tenant = planFor($account, tenant: Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes del Bajío']));
    $this->owner = ownerOf($this->tenant);
});

/** What the form sends, with the accepted words, unless `$over` says otherwise. */
function channelsForm(array $over = []): array
{
    return ['mail_enabled' => true, 'whatsapp_enabled' => true, 'preferred' => 'mail', 'whatsapp_monthly_cap' => null, 'consent_accepted' => true, ...$over];
}

function whatsAppRow(Tenant $tenant): ?TenantChannel
{
    return TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('channel', 'whatsapp')->first();
}

it('shows the owner the channels with their defaults, the words to accept and the extra cost (CUJ 1)', function (): void {
    config(['billing.whatsapp.costs' => [['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 100_000]], 'billing.whatsapp.margin_percent' => 30]);

    $this->actingAs($this->owner)->get(route('tenant.channels.edit', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('tenancy/channels')
        ->where('channels', [
            ['channel' => 'mail', 'enabled' => true, 'preference' => 1, 'monthlyCap' => null, 'consentVersion' => null],
            ['channel' => 'whatsapp', 'enabled' => false, 'preference' => 2, 'monthlyCap' => null, 'consentVersion' => null],
        ])
        ->where('whatsAppAvailable', true)
        ->where('hasPlan', true)
        ->where('consent.version', config('whatsapp.consent.version'))
        ->where('consent.text', str_replace('{empresa}', 'Autopartes del Bajío', config('whatsapp.consent.text')))
        ->where('costNotice', 'WhatsApp tiene costo adicional: cada mensaje aceptado se cobra en tu estado de cuenta a $0.13 MXN más IVA.')
        ->where('whatsAppQuota', ['used' => 0, 'quota' => 1000]));
});

it('says there is no WhatsApp when the function is off in this deployment, or the company has no plan', function (): void {
    config(['whatsapp.enabled' => false]);
    $other = Tenant::factory()->create(['slug' => 'sin-plan']);

    $this->actingAs($this->owner)->get(route('tenant.channels.edit', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('whatsAppAvailable', false));
    config(['whatsapp.enabled' => true]);
    $this->actingAs(ownerOf($other))->get(route('tenant.channels.edit', $other))->assertInertia(fn (Assert $page) => $page->where('hasPlan', false));
});

it('turns WhatsApp on with the accepted words: the row, who, when and which version, and an audit entry (CUJ 1)', function (): void {
    $this->travelTo('2026-11-18 18:00:00');

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['whatsapp_monthly_cap' => 300]))->assertSessionHasNoErrors()->assertRedirect();

    $row = whatsAppRow($this->tenant);
    expect($row?->enabled)->toBeTrue()
        ->and($row->preference)->toBe(2)
        ->and($row->monthly_cap)->toBe(300)
        ->and($row->enabled_by)->toBe($this->owner->id)
        ->and($row->enabled_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($row->consent_wording_version)->toBe(config('whatsapp.consent.version'));
    $entry = AuditEntry::query()->where('event', 'tenancy.channel_enabled')->sole();
    expect($entry->metadata)->toBe(['channel' => 'whatsapp', 'wording_version' => config('whatsapp.consent.version')])
        ->and($entry->actor_id)->toBe($this->owner->id)
        ->and($entry->tenant_id)->toBe($this->tenant->id);
});

it('does not turn WhatsApp on without accepting the words', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['consent_accepted' => false]))->assertSessionHasErrors('consent_accepted');

    expect(whatsAppRow($this->tenant))->toBeNull()->and(AuditEntry::query()->count())->toBe(0);
});

it('asks for the words again when the version changed, and only then', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['consent_wording_version' => '2025-01-01']);

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['consent_accepted' => false]))->assertSessionHasErrors('consent_accepted');
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertSessionHasNoErrors();

    expect(whatsAppRow($this->tenant)?->consent_wording_version)->toBe(config('whatsapp.consent.version'));

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['consent_accepted' => false, 'whatsapp_monthly_cap' => 50]))->assertSessionHasNoErrors();

    expect(whatsAppRow($this->tenant)?->monthly_cap)->toBe(50)
        ->and(AuditEntry::query()->where('event', 'tenancy.channel_enabled')->count())->toBe(1);
});

it('lets the owner change the cap without a new activation', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertSessionHasNoErrors();
    $first = whatsAppRow($this->tenant);

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['consent_accepted' => false, 'whatsapp_monthly_cap' => 700]))->assertSessionHasNoErrors();

    expect(whatsAppRow($this->tenant)?->monthly_cap)->toBe(700)
        ->and(whatsAppRow($this->tenant)?->enabled_at?->equalTo($first?->enabled_at))->toBeTrue();
});

it('turns WhatsApp off with an audit entry and keeps the trace of who accepted', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['whatsapp_enabled' => false, 'consent_accepted' => false]))->assertSessionHasNoErrors();

    expect(whatsAppRow($this->tenant)?->enabled)->toBeFalse()
        ->and(whatsAppRow($this->tenant)?->consent_wording_version)->toBe(config('whatsapp.consent.version'))
        ->and(AuditEntry::query()->where('event', 'tenancy.channel_disabled')->count())->toBe(1);
});

it('keeps a company with the defaults out of the table until it changes something', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['whatsapp_enabled' => false, 'consent_accepted' => false]))->assertSessionHasNoErrors();

    expect(TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('lets the owner put WhatsApp first and the mail second', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['preferred' => 'whatsapp']))->assertSessionHasNoErrors();

    $rows = TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('preference', 'channel')->all();
    expect($rows)->toEqual(['whatsapp' => 1, 'mail' => 2]);
});

it('turns the mail off and on again: the row that turned it off turns it back on', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['mail_enabled' => false, 'preferred' => 'whatsapp']))->assertSessionHasNoErrors();

    expect(TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('channel', 'mail')->sole()->enabled)->toBeFalse();

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['consent_accepted' => false]))->assertSessionHasNoErrors();

    expect(TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('channel', 'mail')->sole()->enabled)->toBeTrue();
});

it('refuses what the rules refuse', function (array $over, string $field): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm($over))->assertSessionHasErrors($field);

    expect(TenantChannel::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
})->with([
    'both channels off' => [['mail_enabled' => false, 'whatsapp_enabled' => false, 'consent_accepted' => false], 'mail_enabled'],
    'a preferred channel that is off' => [['whatsapp_enabled' => false, 'preferred' => 'whatsapp', 'consent_accepted' => false], 'preferred'],
    'a preferred channel that does not exist' => [['preferred' => 'sms'], 'preferred'],
    'a cap of 0' => [['whatsapp_monthly_cap' => 0], 'whatsapp_monthly_cap'],
    'a cap over 100000' => [['whatsapp_monthly_cap' => 100_001], 'whatsapp_monthly_cap'],
    'a cap that is not a number' => [['whatsapp_monthly_cap' => 'mucho'], 'whatsapp_monthly_cap'],
    'a missing mail flag' => [['mail_enabled' => null], 'mail_enabled'],
]);

it('takes a cap of 1 and of 100000', function (int $cap): void {
    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['whatsapp_monthly_cap' => $cap]))->assertSessionHasNoErrors();

    expect(whatsAppRow($this->tenant)?->monthly_cap)->toBe($cap);
})->with([1, 100_000]);

it('does not turn WhatsApp on while the function is off in this deployment', function (): void {
    config(['whatsapp.enabled' => false]);

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertSessionHasErrors('whatsapp_enabled');

    expect(whatsAppRow($this->tenant))->toBeNull();
});

it('does not turn WhatsApp on for a company with no plan', function (): void {
    $other = Tenant::factory()->create(['slug' => 'sin-plan']);

    $this->actingAs(ownerOf($other))->put(route('tenant.channels.update', $other), channelsForm())->assertSessionHasErrors('whatsapp_enabled');
});

it('lets only the owner in: hr and viewer get 403, a stranger 404', function (): void {
    foreach ([TenantRole::Hr, TenantRole::Viewer] as $role) {
        $member = memberOf($this->tenant, $role);
        $this->actingAs($member)->get(route('tenant.channels.edit', $this->tenant))->assertForbidden();
        $this->actingAs($member)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertForbidden();
    }
    $stranger = ownerOf(Tenant::factory()->create());

    $this->actingAs($stranger)->get(route('tenant.channels.edit', $this->tenant))->assertNotFound();
    $this->actingAs($stranger)->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertNotFound();
    expect(whatsAppRow($this->tenant))->toBeNull();
});

it('does not touch the channels of another company', function (): void {
    $other = Tenant::factory()->create();
    TenantChannelFactory::new()->for($other)->whatsAppEnabled()->create(['monthly_cap' => 10]);

    $this->actingAs($this->owner)->put(route('tenant.channels.update', $this->tenant), channelsForm(['whatsapp_monthly_cap' => 99]))->assertSessionHasNoErrors();

    expect(whatsAppRow($other)?->monthly_cap)->toBe(10);
});

it('answers 404 to the guarded save while the staff works as the owner', function (): void {
    $staff = superadmin();
    $this->actingAs($staff)->post(route('companies.impersonate', $this->tenant));

    $this->put(route('tenant.channels.update', $this->tenant), channelsForm())->assertNotFound();

    expect(whatsAppRow($this->tenant))->toBeNull();
});
