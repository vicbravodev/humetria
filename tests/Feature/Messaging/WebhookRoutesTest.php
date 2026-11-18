<?php

declare(strict_types=1);

dataset('webhook endpoints', [
    'meta' => [fn () => test()->postJson(route('messaging.webhooks.meta.receive'), ['entry' => []])],
    'resend' => [fn () => test()->postJson(route('messaging.webhooks.resend.receive'), ['type' => 'email.sent'])],
]);

it('lives at webhooks/whatsapp and webhooks/resend, outside the panel', function (): void {
    expect(route('messaging.webhooks.meta.verify', absolute: false))->toBe('/webhooks/whatsapp')
        ->and(route('messaging.webhooks.meta.receive', absolute: false))->toBe('/webhooks/whatsapp')
        ->and(route('messaging.webhooks.resend.receive', absolute: false))->toBe('/webhooks/resend');
});

it('takes a POST without a CSRF token', function (Closure $post): void {
    expect($post()->getStatusCode())->not->toBe(419);
})->with('webhook endpoints');

it('sets no cookie and starts no session', function (Closure $post): void {
    expect($post()->headers->getCookies())->toBe([]);
})->with('webhook endpoints');

it('answers 429 to the third request of the minute from one address', function (Closure $post): void {
    config(['messaging.webhook_per_minute' => 2]);

    $post();
    $post();

    expect($post()->getStatusCode())->toBe(429);
})->with('webhook endpoints');

it('answers 413 to a body over the maximum, before reading it', function (): void {
    config(['messaging.webhook_max_bytes' => 100]);

    $this->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 101))
        ->assertStatus(413);
});

it('lets a body of exactly the maximum through to the controller', function (): void {
    config(['messaging.webhook_max_bytes' => 100]);

    $status = $this->call('POST', route('messaging.webhooks.resend.receive'), [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 100))->getStatusCode();

    expect($status)->not->toBe(413);
});
