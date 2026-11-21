<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\ChannelPicker;
use App\Modules\Messaging\Support\ChannelRules;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Tenancy\TenantChannelFactory;
use Illuminate\Support\Facades\DB;

mutates(ChannelRules::class);

beforeEach(function (): void {
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => false, 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

function route_(?string $email, ?string $mobile, bool $whatsAppAllowed = true): array
{
    app()->forgetScopedInstances();

    return app(ChannelPicker::class)->route($email, $mobile, $whatsAppAllowed);
}

it('uses mail for a company with no rows (the default of an existing company)', function (): void {
    expect(route_('a@example.com', '+528186875767'))->toBe(['mail' => 'a@example.com']);
});

it('picks WhatsApp when it is on and the owner put it first', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    TenantChannelFactory::new()->for($this->tenant)->create(['channel' => 'mail', 'preference' => 2]);

    expect(route_('a@example.com', '+528186875767'))->toBe(['whatsapp' => '+528186875767']);
});

it('keeps mail first when WhatsApp is second', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();

    expect(route_('a@example.com', '+528186875767'))->toBe(['mail' => 'a@example.com']);
});

it('falls to the next active channel the person has data for', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();

    expect(route_(null, '+528186875767'))->toBe(['whatsapp' => '+528186875767'])
        ->and(route_('', '+528186875767'))->toBe(['whatsapp' => '+528186875767']);
});

it('answers nothing when the person has no data for any active channel', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create();

    expect(route_(null, null))->toBe([])
        ->and(route_('', ''))->toBe([]);
});

it('leaves a person without mobile unreachable when WhatsApp is the only active channel (rule 4)', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->mailDisabled()->create();
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);

    expect(route_('a@example.com', null))->toBe([])
        ->and(route_('a@example.com', '+528186875767'))->toBe(['whatsapp' => '+528186875767']);
});

it('does not use WhatsApp for a number that is not a Mexican mobile', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);

    expect(route_('a@example.com', '+14155550123'))->toBe(['mail' => 'a@example.com'])
        ->and(route_('a@example.com', 'garbage'))->toBe(['mail' => 'a@example.com']);
});

it('never picks WhatsApp when it is not allowed (a reminder)', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);

    expect(route_('a@example.com', '+528186875767', false))->toBe(['mail' => 'a@example.com'])
        ->and(route_(null, '+528186875767', false))->toBe([]);
});

it('skips WhatsApp for a phone that opted out, of this company or global', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    ['hash' => $hash, 'version' => $version] = (new RecipientHash)->current('+528186875767');
    OptOutFactory::new()->create(['tenant_id' => $this->tenant->id, 'recipient_hash' => $hash, 'key_version' => $version]);

    expect(route_('a@example.com', '+528186875767'))->toBe(['mail' => 'a@example.com']);

    DB::table('messaging_opt_outs')->delete();
    OptOutFactory::new()->global()->create(['recipient_hash' => $hash, 'key_version' => $version]);

    expect(route_(null, '+528186875767'))->toBe([]);
});

it('skips WhatsApp when the accepted words are stale, the function is paused, or the company is the demo', function (string $case): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    match ($case) {
        'words' => config(['whatsapp.consent.version' => '2027-01-01']),
        'paused' => config(['whatsapp.paused' => true]),
        'demo' => becomeDemo($this->tenant),
    };

    expect(route_('a@example.com', '+528186875767'))->toBe(['mail' => 'a@example.com']);
})->with(['words', 'paused', 'demo']);

it('makes no query at all with the function off and answers as before', function (): void {
    config(['whatsapp.enabled' => false]);
    app()->forgetScopedInstances();
    $picker = app(ChannelPicker::class);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($picker->route('a@example.com', '+528186875767'))->toBe(['mail' => 'a@example.com'])
        ->and($picker->route(null, '+528186875767'))->toBe([])
        ->and($picker->route('', null))->toBe([])
        ->and($queries)->toBe(0);
});

it('loads the settings and the opt-outs once for many people', function (): void {
    TenantChannelFactory::new()->for($this->tenant)->whatsAppEnabled()->create(['preference' => 1]);
    app()->forgetScopedInstances();
    $picker = app(ChannelPicker::class);
    $picker->route('a@example.com', '+528186875767');
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    foreach (range(1, 20) as $n) {
        $picker->route(null, '+52818687'.str_pad((string) $n, 4, '0', STR_PAD_LEFT));
    }

    expect($queries)->toBe(0);
});
