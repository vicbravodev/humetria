<?php

declare(strict_types=1);

use App\Modules\Messaging\Contracts\WhatsAppGateway;
use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Data\SendOutcome;
use App\Modules\Messaging\Exceptions\RetryableWhatsAppFailure;
use App\Modules\Messaging\Jobs\SendWhatsAppServiceReply;
use App\Modules\Messaging\Models\Pause;
use App\Modules\Messaging\Support\FakeWhatsAppGateway;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryFactory;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

mutates(SendWhatsAppServiceReply::class, App\Modules\Messaging\Actions\HandleMetaEvents::class);

beforeEach(function (): void {
    config([
        'whatsapp.enabled' => true, 'whatsapp.paused' => false,
        'whatsapp.app_secrets' => ['meta-app-secret'], 'messaging.recipient_keys' => [1 => base64_encode('a-key')],
        'whatsapp.replies.opted_out' => 'Texto de baja',
        'whatsapp.replies.opted_in' => 'Texto de alta',
    ]);
    Http::preventStrayRequests();
    Http::fake();
    $this->gateway = new FakeWhatsAppGateway;
    app()->instance(WhatsAppGateway::class, $this->gateway);
});

function wa(string $text, string $id = 'wamid.IN1'): array
{
    return ['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => '5218186875767', 'id' => $id, 'timestamp' => '1795000000', 'type' => 'text', 'text' => ['body' => $text]]]]]]]]];
}

it('has default texts in es-MX that tell how to come back and how to leave', function (): void {
    $fresh = require base_path('config/whatsapp.php');

    expect($fresh['replies']['opted_out'])->toBe('A partir de hoy no recibirá invitaciones por WhatsApp de Humetria. Para volver a recibirlas, responda ALTA.')
        ->and($fresh['replies']['opted_in'])->toBe('A partir de hoy volverá a recibir por este número las invitaciones de sus encuestas. Para darse de baja, responda STOP.');
});

it('answers a STOP with one text job on the whatsapp queue', function (): void {
    Queue::fake();

    $response = postMeta(wa('STOP'));

    expect($response->getContent())->toBe('');
    Queue::assertPushed(SendWhatsAppServiceReply::class, 1);
    Queue::assertPushedOn('whatsapp', SendWhatsAppServiceReply::class, fn (SendWhatsAppServiceReply $job): bool => $job->kind === 'opted_out' && $job->to === '+528186875767');
});

it('answers a tap on «Dejar de recibir» too, and an ALTA or START with the other text', function (): void {
    Queue::fake();

    postMeta(['entry' => [['changes' => [['field' => 'messages', 'value' => ['messages' => [['from' => '5218186875767', 'id' => 'wamid.T', 'type' => 'button', 'button' => ['text' => 'Dejar de recibir']]]]]]]]]);
    postMeta(wa('Alta', 'wamid.A'));
    postMeta(wa('START', 'wamid.S'));

    Queue::assertPushed(SendWhatsAppServiceReply::class, 3);
    Queue::assertPushed(SendWhatsAppServiceReply::class, fn (SendWhatsAppServiceReply $job): bool => $job->kind === 'opted_in');
});

it('answers an ALTA with no opt-out too: it is true and harmless', function (): void {
    Queue::fake();

    postMeta(wa('ALTA'));

    Queue::assertPushed(SendWhatsAppServiceReply::class, fn (SendWhatsAppServiceReply $job): bool => $job->kind === 'opted_in');
});

it('answers nothing to any other text, nor twice to the same message', function (): void {
    Queue::fake();

    postMeta(wa('hola'));
    postMeta(wa('STOP', 'wamid.SAME'));
    postMeta(wa('STOP', 'wamid.SAME'));

    Queue::assertPushed(SendWhatsAppServiceReply::class, 1);
});

it('does not answer a message that is not signed', function (): void {
    Queue::fake();

    postMeta(wa('STOP'), secret: 'wrong');

    Queue::assertNothingPushed();
});

it('does not queue the reply if the opt-out failed halfway', function (): void {
    Queue::fake();
    App\Modules\Messaging\Models\OptOut::creating(fn () => throw new RuntimeException('boom'));
    $this->withoutExceptionHandling();

    try {
        postMeta(wa('STOP'));
    } catch (RuntimeException) {
    }

    Queue::assertNothingPushed();
});

it('travels encrypted, since it carries the phone', function (): void {
    expect(new SendWhatsAppServiceReply('+528186875767', 'opted_out'))->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('sends the confirmation of a STOP as a service text, without a company, a delivery or a charge', function (): void {
    $tenant = Tenant::factory()->create();
    DeliveryFactory::new()->create(['tenant_id' => $tenant->id]);
    $before = App\Modules\Messaging\Models\Delivery::query()->count();

    (new SendWhatsAppServiceReply('+528186875767', 'opted_out'))->handle($this->gateway, app(App\Modules\Messaging\Support\WhatsAppSwitch::class));
    (new SendWhatsAppServiceReply('+528186875767', 'opted_in'))->handle($this->gateway, app(App\Modules\Messaging\Support\WhatsAppSwitch::class));

    expect($this->gateway->texts())->toBe([['+528186875767', 'Texto de baja'], ['+528186875767', 'Texto de alta']])
        ->and(App\Modules\Messaging\Models\Delivery::query()->count())->toBe($before);
});

it('sends nothing while the function is off or paused', function (): void {
    $job = new SendWhatsAppServiceReply('+528186875767', 'opted_out');

    config(['whatsapp.enabled' => false]);
    $job->handle($this->gateway, app(App\Modules\Messaging\Support\WhatsAppSwitch::class));
    config(['whatsapp.enabled' => true, 'whatsapp.paused' => true]);
    $job->handle($this->gateway, app(App\Modules\Messaging\Support\WhatsAppSwitch::class));
    config(['whatsapp.paused' => false]);
    Pause::query()->create(['reason' => 'platform_cap', 'started_at' => now()]);
    $job->handle($this->gateway, app(App\Modules\Messaging\Support\WhatsAppSwitch::class));

    expect($this->gateway->texts())->toBe([]);
});

it('retries a retryable failure with the backoff and the attempts of the send job, and gives up quietly on the rest', function (): void {
    $job = new SendWhatsAppServiceReply('+528186875767', 'opted_out');
    $switch = app(App\Modules\Messaging\Support\WhatsAppSwitch::class);

    expect($job->tries)->toBe(3)->and($job->backoff())->toBe([60, 300]);

    $this->gateway->script(SendOutcome::failed(FailureCause::RateLimited));
    expect(fn () => $job->handle($this->gateway, $switch))->toThrow(RetryableWhatsAppFailure::class);

    Log::spy();
    $this->gateway->script(SendOutcome::failed(FailureCause::NoWhatsappAccount));
    $job->handle($this->gateway, $switch);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $event, array $context = []): bool => $event === 'whatsapp.service_reply_failed'
        && $context === ['kind' => 'opted_out', 'cause' => FailureCause::NoWhatsappAccount->value])->once();
});
