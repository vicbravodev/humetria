<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\RecordOptOut;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\Log;

mutates(RecordOptOut::class);

const STOP_PHONE = '+528186875767';

beforeEach(function (): void {
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
});

function deliveryFor(Tenant $tenant, string $phone, int $key = 1, string $channel = 'whatsapp'): void
{
    $hash = (new RecipientHash)->all($phone)[$key - 1];
    DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'channel' => $channel, 'recipient_hash' => $hash, 'key_version' => $key]);
}

it('records the opt-out in every company that wrote to the phone and in no other', function (): void {
    [$a, $b, $c] = [Tenant::factory()->create(), Tenant::factory()->create(), Tenant::factory()->create()];
    deliveryFor($a, STOP_PHONE);
    deliveryFor($b, STOP_PHONE);
    deliveryFor($c, '+528100000000');
    Log::spy();

    $recorded = app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    $hash = (new RecipientHash)->current(STOP_PHONE)['hash'];
    expect($recorded)->toBe(2)
        ->and(OptOut::query()->where('recipient_hash', $hash)->pluck('tenant_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and(OptOut::query()->first()?->source)->toBe('stop');
    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.opt_out_recorded'
        && $context === ['companies' => 2, 'global' => false])->once();
});

it('records a global opt-out when no company ever wrote to the phone', function (): void {
    $recorded = app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    $row = OptOut::query()->firstOrFail();
    expect($recorded)->toBe(1)->and($row->tenant_id)->toBeNull()
        ->and($row->recipient_hash)->toBe((new RecipientHash)->current(STOP_PHONE)['hash'])
        ->and($row->key_version)->toBe(1);
});

it('ignores mail deliveries, which keep no phone', function (): void {
    $a = Tenant::factory()->create();
    DeliveryFactory::new()->mail()->create(['tenant_id' => $a->id]);

    app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    expect(OptOut::query()->firstOrFail()->tenant_id)->toBeNull();
});

it('finds the companies by a hash made with an older key and records under the newest', function (): void {
    $a = Tenant::factory()->create();
    deliveryFor($a, STOP_PHONE, 1);
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key'), 2 => base64_encode('newer')]]);

    app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    $row = OptOut::query()->firstOrFail();
    expect($row->tenant_id)->toBe($a->id)
        ->and($row->key_version)->toBe(2)
        ->and($row->recipient_hash)->toBe((new RecipientHash)->current(STOP_PHONE)['hash']);
});

it('is idempotent: a second STOP writes nothing new', function (): void {
    deliveryFor(Tenant::factory()->create(), STOP_PHONE);

    app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');
    $second = app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    expect($second)->toBe(0)->and(OptOut::query()->count())->toBe(1);
});

it('writes neither the phone nor the hash to the logs', function (): void {
    Log::spy();

    app(RecordOptOut::class)->handle(STOP_PHONE, 'stop');

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => ! str_contains((string) json_encode($context), '8186875767')
        && ! str_contains((string) json_encode($context), (new RecipientHash)->current(STOP_PHONE)['hash']));
});

it('records a global opt-out only once', function (): void {
    app(RecordOptOut::class)->handle(STOP_PHONE, 'operator');
    app(RecordOptOut::class)->handle(STOP_PHONE, 'operator');

    expect(OptOut::query()->count())->toBe(1);
});
