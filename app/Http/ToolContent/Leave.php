<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Vacaciones y permisos (/vacaciones-y-permisos). What it says of the law comes from docs/normas/vacaciones/; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Leave
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Días de vacaciones por ley: saldos, solicitudes y permisos',
            'description' => 'Calcula los días de vacaciones por ley de cada persona, 12 desde el primer año, con solicitudes que aprueba su jefe y la constancia anual de la LFT.',
            'name' => 'Vacaciones y permisos',
            'family' => 'Personas y sueldos',
            'chip' => 'Vacaciones dignas · LFT, art. 76',
            'h1' => 'Los días de vacaciones por ley, calculados solos y pedidos desde el celular',
            'lead' => 'Cada persona ve su saldo conforme a la ley, o a tu política si da más, y pide sus días desde su portal. Su jefe aprueba, el calendario del equipo muestra quién falta y el expediente guarda la solicitud y la constancia anual.',
            'proof' => 'Aplica sola la tabla del artículo 76. Tu política puede dar más días, nunca menos, y cada año queda la constancia con acuse.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'Desde 2023 la Ley Federal del Trabajo da al menos 12 días de vacaciones desde el primer año, y pide constancia de cada periodo.',
                'facts' => [
                    [
                        'label' => 'Primer año de servicio',
                        'value' => '12 días laborables',
                        'note' => 'LFT, art. 76',
                    ],
                    [
                        'label' => 'Prima vacacional',
                        'value' => 'Al menos 25 %',
                        'note' => 'LFT, art. 80',
                    ],
                    [
                        'label' => 'Plazo para darlas',
                        'value' => '6 meses',
                        'note' => 'Después del aniversario · art. 81',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Al cumplir un año, al menos 12 días laborables pagados, y 2 más por cada año hasta 20. Desde el sexto año, 2 días más por cada cinco de servicio.',
                        'source' => 'LFT, art. 76',
                    ],
                    [
                        'text' => 'La persona disfruta al menos 12 días continuos y puede distribuir el resto como lo requiera.',
                        'source' => 'LFT, art. 78',
                    ],
                    [
                        'text' => 'Las vacaciones no se cambian por dinero; si la relación termina antes del año, corresponde la parte proporcional.',
                        'source' => 'LFT, art. 79',
                    ],
                    [
                        'text' => 'Cada año el patrón entrega una constancia con la antigüedad, el periodo que corresponde y la fecha en que debe disfrutarse.',
                        'source' => 'LFT, art. 81',
                    ],
                ],
                'note' => 'En vigor desde el 1 de enero de 2023.',
            ],
            'guideLink' => false,
            'pick' => [
                'title' => '¿Cuántos días le tocan?',
                'lead' => 'Cada periodo nace completo en el aniversario, con la tabla del artículo 76. Tu política puede dar más, nunca menos.',
                'question' => 'Años de servicio cumplidos',
                'options' => [
                    [
                        'label' => '1 año',
                        'answer' => '12 días',
                        'detail' => 'Laborables, pagados, con prima vacacional de al menos 25 %.',
                    ],
                    [
                        'label' => '2 años',
                        'answer' => '14 días',
                        'detail' => 'Dos días más por cada año, hasta llegar a 20.',
                    ],
                    [
                        'label' => '3 años',
                        'answer' => '16 días',
                        'detail' => 'Dos días más por cada año, hasta llegar a 20.',
                    ],
                    [
                        'label' => '4 años',
                        'answer' => '18 días',
                        'detail' => 'Dos días más por cada año, hasta llegar a 20.',
                    ],
                    [
                        'label' => '5 años',
                        'answer' => '20 días',
                        'detail' => 'Desde el sexto año, dos días más por cada cinco de servicio.',
                    ],
                ],
            ],
            'steps' => [
                [
                    'title' => 'Eliges la política',
                    'text' => 'La de la ley o una que dé más días o más prima. Los festivos de tu empresa, también.',
                    'shot' => 'leave-05-policy',
                    'alt' => 'La política de vacaciones de la empresa contra la ley',
                ],
                [
                    'title' => 'La persona pide desde su portal',
                    'text' => 'Elige sus días en el calendario y solo se descuentan los laborables.',
                    'shot' => 'leave-11-portal-request-mobile',
                    'alt' => 'Pedir días desde el portal en el celular',
                ],
                [
                    'title' => 'El jefe aprueba',
                    'text' => 'Si no responde en 3 días hábiles, la solicitud pasa a RH. Todo queda en el expediente.',
                    'shot' => 'leave-04-requests',
                    'alt' => 'Las solicitudes por aprobar',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Calendario del equipo',
                    'title' => 'Quién falta y cuándo, para planear la cobertura',
                    'text' => 'El calendario muestra las vacaciones aprobadas y las solicitudes pendientes de todo el equipo.',
                    'bullets' => [
                        'Las ausencias del mes de un vistazo',
                        'Solicitudes pendientes en el mismo lugar',
                        'Solo dice vacaciones o permiso, nunca el motivo',
                    ],
                    'shot' => 'leave-02-calendar',
                    'alt' => 'Calendario de vacaciones del equipo',
                    'caption' => 'Vacaciones aprobadas. Solicitudes por aprobar.',
                ],
                [
                    'kicker' => 'Aprobaciones',
                    'title' => 'El jefe aprueba en un clic, con el saldo a la vista',
                    'text' => 'Cada solicitud muestra los días pedidos y el saldo de la persona, junto al calendario del equipo.',
                    'bullets' => [
                        'Aprobar o rechazar con motivo',
                        'Pasa a RH si el jefe no responde',
                        'Queda quién aprobó y cuándo',
                    ],
                    'shot' => 'leave-04-requests',
                    'alt' => 'Solicitudes por aprobar con el detalle de una',
                    'caption' => 'Detalle de la solicitud. Aprobar o rechazar.',
                ],
                [
                    'kicker' => 'Portal de la persona',
                    'title' => 'Su saldo y sus días, en su celular',
                    'text' => 'La persona ve cuántos días tiene, cuándo vencen y pide los suyos sin llenar formatos.',
                    'bullets' => [
                        'Saldo por ley o por tu política',
                        'Avisa si no cumple los 12 días continuos',
                        'Permisos de paternidad, maternidad y más, listos',
                    ],
                    'shot' => 'leave-10-portal-mobile',
                    'alt' => 'Mis vacaciones en el celular',
                    'caption' => '17 días disponibles. Pedir días.',
                ],
            ],
            'beforeAfterTitle' => 'Del formato en papel al saldo que se calcula solo',
            'beforeAfter' => [
                [
                    'before' => 'Formatos de solicitud y firmas en papel',
                    'after' => 'Solicitud desde el celular y aprobación del jefe',
                ],
                [
                    'before' => 'Saldos en Excel que nadie cuadra',
                    'after' => 'Saldo por ley o por tu política, calculado solo',
                ],
                [
                    'before' => 'Ausencias que se descubren el mismo día',
                    'after' => 'El calendario del equipo',
                ],
                [
                    'before' => 'Sin constancia anual',
                    'after' => 'Constancia con acuse de la persona',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Saldos al día sin cuadrar Excel',
                    'text' => 'Políticas, festivos, periodos por vencer y la evidencia de cada movimiento.',
                ],
                [
                    'who' => 'Jefes',
                    'title' => 'Aprueban y planean la cobertura',
                    'text' => 'Ven solo a su gente, con el calendario del equipo.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Sabe cuántos días tiene',
                    'text' => 'Y los pide desde su celular, sin formatos.',
                ],
            ],
            'privacy' => [
                'title' => 'El motivo de un permiso no es asunto del equipo',
                'items' => [
                    [
                        'title' => 'El tipo, solo a quien debe',
                        'text' => 'Una maternidad o una adopción solo la ven RH y quien aprueba.',
                    ],
                    [
                        'title' => 'Calendario discreto',
                        'text' => 'El equipo solo ve si la persona está de vacaciones o con permiso.',
                    ],
                    [
                        'title' => 'Cada movimiento queda',
                        'text' => 'Solicitudes, aprobaciones y ajustes del saldo, sin borrar nada.',
                    ],
                    [
                        'title' => 'Cada jefe, su gente',
                        'text' => 'Un jefe no ve el saldo de quien no le reporta.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿Cuántos días de vacaciones me tocan por ley?',
                    'answer' => 'Al cumplir el primer año, 12 días laborables; 14 al segundo, 16 al tercero, 18 al cuarto y 20 al quinto. Después siguen subiendo con la antigüedad (LFT, art. 76).',
                ],
                [
                    'question' => '¿Desde cuándo aplican los 12 días?',
                    'answer' => 'Desde el 1 de enero de 2023, cuando entró en vigor la reforma a los artículos 76 y 78.',
                ],
                [
                    'question' => '¿Se pueden dividir las vacaciones?',
                    'answer' => 'Sí, pero la persona disfruta al menos 12 días continuos (LFT, art. 78).',
                ],
                [
                    'question' => '¿Se pueden pagar en lugar de tomarlas?',
                    'answer' => 'No mientras dura la relación. Si termina antes del año, corresponde la parte proporcional (LFT, art. 79).',
                ],
                [
                    'question' => '¿Cuánto es la prima vacacional?',
                    'answer' => 'Al menos 25 % sobre los salarios del periodo de vacaciones (LFT, art. 80).',
                ],
                [
                    'question' => '¿En qué plazo deben darse?',
                    'answer' => 'Dentro de los seis meses siguientes al aniversario, con una constancia anual (LFT, art. 81).',
                ],
            ],
            'related' => [
                [
                    'key' => 'attendance',
                    'why' => 'Las vacaciones no cuentan como faltas.',
                ],
                [
                    'key' => 'org_chart',
                    'why' => 'Cada solicitud llega a su jefe directo.',
                ],
                [
                    'key' => 'compensation',
                    'why' => 'La prima vacacional entra en el costo de un aumento.',
                ],
            ],
            'film' => [
                'name' => 'humetria-leave',
                'title' => 'Vacaciones y permisos en Humetría',
                'description' => 'La persona ve su saldo de vacaciones conforme a la ley y a la política de su empresa, elige sus días en el calendario de su portal y toca Solicitar; su jefe aprueba, la ausencia aparece en el calendario del equipo y el expediente guarda la solicitud y la constancia anual.',
                'duration' => 26,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
