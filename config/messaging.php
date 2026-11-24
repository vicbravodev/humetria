<?php

declare(strict_types=1);

/*
 * Messaging: transport of WhatsApp and of the mail events (docs/superpowers/specs/2026-11-18-whatsapp-channel.md).
 * Every value marked "Supuesto por confirmar (Victor)" is an assumption to confirm; none blocks a commit.
 */
return [
    // HMAC keys of the recipient hash, `1:<base64>,2:<base64>` => [1 => '<base64>', 2 => '<base64>']. The newest (highest version)
    // signs new rows; an old one is not removed while an opt-out with its version exists (rule 6).
    'recipient_keys' => collect(explode(',', (string) env('MESSAGING_RECIPIENT_KEYS', '')))
        ->filter(fn (string $pair): bool => preg_match('/^\d+:.+$/', trim($pair)) === 1)
        ->mapWithKeys(fn (string $pair): array => [(int) explode(':', trim($pair), 2)[0] => explode(':', trim($pair), 2)[1]])
        ->all(),

    // Supuesto por confirmar (Victor, regla 14): months the deliveries and their events are kept.
    'retention_months' => (int) env('MESSAGING_RETENTION_MONTHS', 12),

    // Supuesto por confirmar (Victor, regla 14): days a webhook event is remembered, against a repeated one.
    'webhook_event_days' => 7,

    // Supuesto por confirmar (Victor, regla 8): minutes a status of Meta waits for its message id to be written.
    'orphan_minutes' => 60,

    // Supuesto por confirmar (Victor, regla 8): hours an `unknown` message waits to be resolved before it fails as `unresolved`.
    'unknown_hours' => 24,

    // Supuesto por confirmar (Victor): requests a minute per address to the webhook routes of the providers.
    'webhook_per_minute' => (int) env('MESSAGING_WEBHOOK_PER_MINUTE', 6000),

    // Supuesto por confirmar (Victor): the largest body a webhook accepts, in bytes (413 above it, before reading the JSON).
    'webhook_max_bytes' => (int) env('MESSAGING_WEBHOOK_MAX_BYTES', 262144),
];
