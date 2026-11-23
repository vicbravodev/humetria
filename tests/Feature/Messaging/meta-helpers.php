<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;

const META_SECRET = 'meta-app-secret';

/** @param array<string, mixed> $payload */
function postMeta(array|string $payload, ?string $secret = META_SECRET, ?string $header = null): TestResponse
{
    $raw = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($header !== null || $secret !== null) {
        $server['HTTP_X_HUB_SIGNATURE_256'] = $header ?? 'sha256='.hash_hmac('sha256', $raw, (string) $secret);
    }

    return test()->call('POST', route('messaging.webhooks.meta.receive'), [], [], [], $server, $raw);
}
