<?php

declare(strict_types=1);

/*
 * Messaging: transport of WhatsApp and of the mail events (docs/superpowers/specs/2026-11-18-whatsapp-channel.md).
 * Every value marked "Supuesto por confirmar (Victor)" is an assumption to confirm; none blocks a commit.
 */
return [
    // Supuesto por confirmar (Victor): requests a minute per address to the webhook routes of the providers.
    'webhook_per_minute' => (int) env('MESSAGING_WEBHOOK_PER_MINUTE', 6000),

    // Supuesto por confirmar (Victor): the largest body a webhook accepts, in bytes (413 above it, before reading the JSON).
    'webhook_max_bytes' => (int) env('MESSAGING_WEBHOOK_MAX_BYTES', 262144),
];
