<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Catálogo comercial
|--------------------------------------------------------------------------
|
| Una entrada por versión del catálogo; cada suscripción guarda la versión con la que se cotizó, así que publicar una
| nueva nunca cambia lo que ya se pactó. Todo importe va en centavos enteros de la moneda (nunca flotantes) y todo
| porcentaje en enteros. Los precios negociados no viven aquí: van en la suscripción.
|
| Tarifas y mínimos del handoff de monetización v1.0: Jornada $39 (mínimo $1,990), Cumplimiento $49 (mínimo $2,990) e
| Integral $99 (mínimo $4,990) por persona al mes.
|
*/

return [

    'currency' => 'MXN',

    /* La versión con la que se cotiza una suscripción nueva. */
    'current_version' => 'v1',

    'versions' => [

        'v1' => [
            'effective_on' => '2026-11-09',

            /* IVA: una línea aparte sobre el subtotal. */
            'vat_percent' => 16,

            /* Cada centro de trabajo más allá de los incluidos en el plan; no entra al descuento de canal. */
            'extra_center_cents' => 14_900,

            /* Con más personas que esto no hay tabla: se cotiza a mano (o rige un precio negociado). */
            'quotation_above' => ['direct' => 100, 'channel_compliance' => 250, 'channel_integral' => 250],

            /* Canal Cumplimiento: banda completa por plantilla. «Hasta N personas» => importe. */
            'channel_compliance_bands' => [50 => 249_000, 100 => 399_000, 250 => 799_000],

            /* Canal Integral: descuento sobre el importe directo, si la cuenta tiene suficientes empresas con Integral pagado
               (sin pruebas, y sin empresas suspendidas ni archivadas). */
            'channel_integral' => ['discount_percent' => 20, 'minimum_companies' => 3],

            'plans' => [
                'jornada' => [
                    'rate_cents' => 3_900,
                    'minimum_cents' => 199_000,
                    'included_centers' => 1,
                    'modules' => ['attendance', 'vacations', 'org_chart'],
                    'quotas' => ['assistant_tokens' => 250_000, 'email_per_seat' => 8, 'email_minimum' => 500, 'storage_bytes' => 5_368_709_120],
                ],
                'cumplimiento' => [
                    'rate_cents' => 4_900,
                    'minimum_cents' => 299_000,
                    'included_centers' => 2,
                    'modules' => ['nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate'],
                    'quotas' => ['assistant_tokens' => 500_000, 'email_per_seat' => 8, 'email_minimum' => 500, 'storage_bytes' => 5_368_709_120],
                ],
                'integral' => [
                    'rate_cents' => 9_900,
                    'minimum_cents' => 499_000,
                    'included_centers' => 3,
                    'modules' => ['attendance', 'vacations', 'org_chart', 'nom035', 'nom037', 'seating', 'complaints', 'pulse', 'climate'],
                    'quotas' => ['assistant_tokens' => 2_000_000, 'email_per_seat' => 8, 'email_minimum' => 500, 'storage_bytes' => 5_368_709_120],
                ],
            ],
        ],

    ],

];
