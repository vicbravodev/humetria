<?php

declare(strict_types=1);

/*
 * WhatsApp (Cloud API de Meta): un número central de Humetria, plantillas UTILITY, solo salida.
 * docs/superpowers/specs/2026-11-18-whatsapp-channel.md. Cada valor marcado "Supuesto por confirmar (Victor)" es un supuesto.
 */
return [
    // The function does not exist in this deployment while false (the null gateway). Operated by Victor in Dokploy.
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    // Emergency stop: no WhatsApp message leaves for any company; the function stays on.
    'paused' => (bool) env('WHATSAPP_PAUSED', false),

    'base_url' => 'https://graph.facebook.com',

    // Verificado en el sandbox el 2026-11-18: v23.0 responde 200 (envío, números, plantillas); la página de Meta ya muestra v25.0.
    'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // The secret of the Meta app signs every webhook body. During a rotation both the current and the previous one are accepted.
    'app_secrets' => array_values(array_filter([env('WHATSAPP_APP_SECRET'), env('WHATSAPP_APP_SECRET_PREVIOUS')])),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),

    'language' => 'es_MX',

    // Templates that go out only once Meta approves them; until then their message goes by mail. Victor turns each on in Dokploy.
    'templates' => [
        // The invitation to the portal (WorkerAccess), submitted on 2026-11-26.
        'worker_invite_v1' => (bool) env('WHATSAPP_WORKER_INVITE', false),
    ],
    'timeout_seconds' => 10,

    // Supuesto por confirmar (Victor, antes de la Fase 2 (c)): sends a second, against the limit of the number.
    'max_per_second' => (int) env('WHATSAPP_MAX_PER_SECOND', 20),

    // Supuesto por confirmar (Victor, antes de la Fase 3): messages a day per company.
    'daily_cap_per_company' => (int) env('WHATSAPP_DAILY_CAP_PER_COMPANY', 1000),

    // Supuesto por confirmar (Victor, antes de la Fase 3): messages a month for the whole platform.
    'platform_monthly_cap' => (int) env('WHATSAPP_PLATFORM_MONTHLY_CAP', 20000),

    // Supuesto por confirmar (Victor, antes de la Fase 3): the automatic pause when blocks and spam reports pile up.
    'auto_pause' => [
        'failure_rate_percent' => 5,
        'minimum_messages' => 50,
        'window_minutes' => 60,
    ],

    // Supuesto por confirmar (Victor, antes de la Fase 2): the words the owner accepts and the notice of the extra cost.
    'consent' => [
        'version' => '2026-11-18',
        'text' => 'Declaro que las personas cuyo celular {empresa} registra en Humetria aceptaron recibir por WhatsApp, desde el número de Humetria, las invitaciones a sus encuestas, y que pueden darse de baja respondiendo STOP.',
    ],
    // Supuesto por confirmar (Victor): the answers of service to a STOP and to an ALTA or START. Free text inside the 24 hours that
    // the person's own message opens; not a template, not metered, not charged. Meta's policy asks to honor a STOP always.
    'replies' => [
        'opted_out' => 'A partir de hoy no recibirá invitaciones por WhatsApp de Humetria. Para volver a recibirlas, responda ALTA.',
        'opted_in' => 'A partir de hoy volverá a recibir por este número las invitaciones de sus encuestas. Para darse de baja, responda STOP.',
    ],

    'cost_notice' => 'WhatsApp tiene costo adicional: cada mensaje aceptado se cobra en tu estado de cuenta a {precio} más IVA.',
];
