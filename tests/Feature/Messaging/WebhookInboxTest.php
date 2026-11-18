<?php

declare(strict_types=1);

use App\Modules\Messaging\Models\WebhookEvent;
use App\Modules\Messaging\Support\WebhookInbox;

mutates(WebhookInbox::class);

it('lets an event through the first time and refuses it the second', function (): void {
    $inbox = new WebhookInbox;

    expect($inbox->firstTime('meta', 'wamid.1:delivered'))->toBeTrue()
        ->and($inbox->firstTime('meta', 'wamid.1:delivered'))->toBeFalse()
        ->and(WebhookEvent::query()->count())->toBe(1);
});

it('counts the same id of another provider as another event', function (): void {
    $inbox = new WebhookInbox;

    expect($inbox->firstTime('meta', 'same'))->toBeTrue()
        ->and($inbox->firstTime('resend', 'same'))->toBeTrue();
});

it('keeps the orphan status to reprocess it, without any phone', function (): void {
    $orphan = ['provider_message_id' => 'wamid.9', 'callback_id' => 'abc', 'status' => 'delivered', 'timestamp' => 1_700_000_000, 'error_code' => null];

    (new WebhookInbox)->firstTime('meta', 'wamid.9:delivered', $orphan);

    $event = WebhookEvent::query()->firstOrFail();
    expect($event->orphan)->toEqualCanonicalizing($orphan)
        ->and($event->processed_at)->toBeNull()
        ->and($event->received_at)->not->toBeNull();
});

it('uses a random UUID, never one that sorts by time', function (): void {
    (new WebhookInbox)->firstTime('meta', 'x');

    expect(WebhookEvent::query()->firstOrFail()->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});
