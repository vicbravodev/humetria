<?php

declare(strict_types=1);

use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Support\Facades\Http;

mutates(App\Modules\Messaging\Actions\HandleMetaEvents::class);

beforeEach(function (): void {
    config(['whatsapp.app_secrets' => ['meta-app-secret'], 'messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
    Http::preventStrayRequests();
    Http::fake();
});

function inbound(string $text, string $id = 'wamid.IN1', string $from = '5218186875767', string $type = 'text'): array
{
    return ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => $from, 'id' => $id, 'timestamp' => '1795000000', 'type' => $type, 'text' => ['body' => $text]]]]]]]]];
}

function wroteTo(Tenant $tenant, string $phone = '+528186875767'): void
{
    DeliveryFactory::new()->create(['tenant_id' => $tenant->id, 'recipient_hash' => (new RecipientHash)->current($phone)['hash']]);
}

it('records the opt-out of every company that wrote to the phone and answers nothing (CUJ 4)', function (): void {
    [$a, $b, $c] = [Tenant::factory()->create(), Tenant::factory()->create(), Tenant::factory()->create()];
    wroteTo($a);
    wroteTo($b);
    wroteTo($c, '+528100000000');

    $response = postMeta(inbound('STOP'));

    expect($response->getStatusCode())->toBe(200)->and($response->getContent())->toBe('')
        ->and(OptOut::query()->pluck('tenant_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
    Http::assertNothingSent();
});

it('records a global opt-out when no company ever wrote to the phone', function (): void {
    postMeta(inbound('STOP'));

    $row = OptOut::query()->sole();
    expect($row->tenant_id)->toBeNull()->and($row->recipient_hash)->toBe((new RecipientHash)->current('+528186875767')['hash']);
});

it('reads the words STOP, BAJA, ALTO and CANCELAR however they are written', function (string $text): void {
    postMeta(inbound($text));

    expect(OptOut::query()->count())->toBe(1);
})->with(['stop', 'STOP', 'Baja.', 'ALTO!', '  cancelar  ', 'Cancelar.!', "Stop\n"]);

it('does not read any other text as a STOP, and leaves no trace of it', function (string $text): void {
    postMeta(inbound($text));

    expect(OptOut::query()->count())->toBe(0)->and(WebhookEvent::query()->count())->toBe(0);
})->with(['no gracias', 'STOP por favor', 'hola', '', 'stoppp', 'alto ahí']);

it('ignores a STOP that is not a text message, or has no sender', function (): void {
    postMeta(inbound('STOP', type: 'image'));
    postMeta(inbound('STOP', id: 'wamid.IN2', from: 'garbage'));
    postMeta(['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['id' => 'wamid.IN3', 'type' => 'text', 'text' => ['body' => 'STOP']]]]]]]]]);

    expect(OptOut::query()->count())->toBe(0);
});

it('hashes the wa_id as the stored mobile: 521 and 52 are the same Mexican number', function (): void {
    $tenant = Tenant::factory()->create();
    wroteTo($tenant, '+528186875767');

    postMeta(inbound('STOP', from: '528186875767'));

    expect(OptOut::query()->sole()->tenant_id)->toBe($tenant->id);
});

it('processes a message id once', function (): void {
    $tenant = Tenant::factory()->create();
    wroteTo($tenant);

    postMeta(inbound('STOP', id: 'wamid.SAME'));
    postMeta(inbound('STOP', id: 'wamid.SAME'));

    expect(WebhookEvent::query()->where('event_id', 'message:wamid.SAME')->count())->toBe(1)->and(OptOut::query()->count())->toBe(1);
});

it('does not log the phone or its hash', function (): void {
    Illuminate\Support\Facades\Log::spy();

    postMeta(inbound('STOP'));

    Illuminate\Support\Facades\Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => ! str_contains((string) json_encode($context), '8186875767'));
});
