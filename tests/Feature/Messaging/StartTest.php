<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

mutates(App\Modules\Messaging\Actions\HandleMetaEvents::class, App\Modules\Messaging\Actions\LiftOptOut::class);

beforeEach(function (): void {
    config(['whatsapp.app_secrets' => ['meta-app-secret'], 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    Http::preventStrayRequests();
    Http::fake();
});

/** A message of the person to the number of Humetria, as Meta sends it: wa_id `521` + 10 digits. */
function said(string $text, string $id = 'wamid.IN1', string $from = '5218186875767'): array
{
    return ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => $from, 'id' => $id, 'timestamp' => '1795000000', 'type' => 'text', 'text' => ['body' => $text]]]]]]]]];
}

function wroteToPhone(Tenant $tenant, string $phone = '+528186875767'): void
{
    DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'recipient_hash' => (new RecipientHash)->current($phone)['hash']]);
}

it('lifts the opt-out of every company the STOP covered when the person says ALTA', function (): void {
    [$a, $b] = [Tenant::factory()->create(), Tenant::factory()->create()];
    wroteToPhone($a);
    wroteToPhone($b);
    postMeta(said('STOP'));
    expect(OptOut::query()->count())->toBe(2);

    $response = postMeta(said('ALTA', id: 'wamid.IN2'));

    expect($response->getStatusCode())->toBe(200)->and(OptOut::query()->count())->toBe(0);
});

it('lifts a global opt-out too, and only the one of that phone', function (): void {
    postMeta(said('STOP'));
    postMeta(said('STOP', id: 'wamid.IN9', from: '5218100000000'));

    postMeta(said('START', id: 'wamid.IN2'));

    expect(OptOut::query()->sole()->recipient_hash)->toBe((new RecipientHash)->current('+528100000000')['hash']);
});

it('reads ALTA and START however they are written', function (string $text): void {
    postMeta(said('STOP'));

    postMeta(said($text, id: 'wamid.IN2'));

    expect(OptOut::query()->count())->toBe(0);
})->with(['alta', 'Start', ' start ', 'Alta.', 'START!', "alta\n"]);

it('does not lift the opt-out for any other text, and leaves no trace of it', function (string $text): void {
    postMeta(said('STOP'));

    postMeta(said($text, id: 'wamid.IN2'));

    expect(OptOut::query()->count())->toBe(1)->and(WebhookEvent::query()->pluck('event_id')->all())->toBe(['message:wamid.IN1']);
})->with(['dar de alta', 'ALTA por favor', 'starts', 'hola', '']);

it('does nothing odd when the person says ALTA without an opt-out', function (): void {
    $response = postMeta(said('ALTA'));

    expect($response->getStatusCode())->toBe(200)->and(OptOut::query()->count())->toBe(0)->and(AuditEntry::query()->count())->toBe(0);
});

it('lifts once when Meta repeats the message', function (): void {
    postMeta(said('STOP'));
    postMeta(said('ALTA', id: 'wamid.SAME'));
    postMeta(said('STOP', id: 'wamid.IN3'));
    postMeta(said('ALTA', id: 'wamid.SAME'));

    expect(OptOut::query()->count())->toBe(1);
});

it('logs and audits the lift without the phone, its hash or the company link', function (): void {
    Log::spy();
    postMeta(said('STOP'));

    postMeta(said('ALTA', id: 'wamid.IN2'));

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'messaging.opt_out_lifted' && $context === ['opt_outs' => 1]);
    $entry = AuditEntry::query()->sole();
    expect($entry->event)->toBe('messaging.opt_out_lifted')->and($entry->subject_id)->toBeNull()
        ->and(str_contains((string) json_encode($entry->metadata), '8186875767'))->toBeFalse();
});
