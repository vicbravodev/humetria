<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Organigrama (/organigrama). What it says of the law comes from app/Modules/Organization; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class OrgChart
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Organigrama de empresa en línea, desde tu archivo',
            'description' => 'Haz el organigrama de tu empresa a partir de quién reporta a quién: cárgalo desde tu archivo de personal y cada jefe ve y aprueba solo a su gente.',
            'name' => 'Organigrama',
            'family' => 'Personas y sueldos',
            'chip' => 'Sin límite de niveles · desde tu archivo de personal',
            'h1' => 'Tu organigrama cambió ayer. Aquí ya está al día',
            'lead' => 'El organigrama sale de quién reporta a quién: lo cargas desde tu archivo de personal y se dibuja solo. Cuando un jefe se va, te pide reasignar a su equipo, y cada jefe ve en su portal solo a quienes le reportan.',
            'proof' => 'Se dibuja solo a partir de tu archivo, nadie queda sin jefe y las aprobaciones de todas las herramientas salen de aquí.',
            'heroPhone' => null,
            'phoneAlt' => null,
            'norm' => null,
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Cargas tu archivo',
                    'text' => 'Un CSV con cada persona y su jefe directo. Antes de guardar ves qué se va a crear y qué se va a cambiar.',
                ],
                [
                    'title' => 'Se dibuja el organigrama',
                    'text' => 'Con puestos, áreas y centros, sin límite de niveles y sin dibujar a mano.',
                ],
                [
                    'title' => 'Se mantiene al día',
                    'text' => 'Cada alta, baja o cambio de jefe lo actualiza. Si un jefe se va, te pide reasignar a su equipo.',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Siempre al día',
                    'title' => 'Quién reporta a quién, sin dibujar nada',
                    'text' => 'El organigrama se arma con la línea de reporte de tu gente y cambia con ella.',
                    'bullets' => [
                        'Totales de cada equipo a la vista',
                        'Por área y por centro de trabajo',
                        'Sin límite de niveles',
                    ],
                    'shot' => 'org-chart-01-chart',
                    'alt' => 'Organigrama con la dirección y cuatro gerencias',
                    'caption' => 'Dirección y gerencias. Personas por equipo.',
                ],
                [
                    'kicker' => 'Cada persona',
                    'title' => 'El equipo de cada jefe, en un clic',
                    'text' => 'Abre a cualquier persona y ve a su jefe y a quienes le reportan.',
                    'bullets' => [
                        'Jefe directo y reportes directos',
                        'Puesto, área y centro',
                        'Exporta la línea de reporte a CSV, sin datos de contacto',
                    ],
                    'shot' => 'org-chart-02-team',
                    'alt' => 'Organigrama con el panel lateral de una persona',
                    'caption' => 'Su jefe y su equipo. Exportable a CSV.',
                ],
                [
                    'kicker' => 'Base de todo',
                    'title' => 'Las aprobaciones llegan al jefe correcto',
                    'text' => 'Las vacaciones, los permisos y los resultados por jefe salen del organigrama: si cambia un jefe, todo se reacomoda.',
                    'bullets' => [
                        'Cada jefe ve solo a su gente en su portal',
                        'Al dar de baja a un jefe, reasignas a su equipo',
                        'La historia de cada persona se conserva',
                    ],
                    'shot' => 'dashboard-10-worker-home-mobile',
                    'alt' => 'Portal de la persona con Mi equipo',
                    'caption' => 'Mi equipo, en el portal. Aprueba solo a su gente.',
                ],
            ],
            'beforeAfterTitle' => 'Del organigrama en PowerPoint al que se actualiza solo',
            'beforeAfter' => [
                [
                    'before' => 'Un PowerPoint que nadie actualiza',
                    'after' => 'Se dibuja solo con cada alta, baja o cambio',
                ],
                [
                    'before' => 'Equipos que quedan bajo un jefe que ya se fue',
                    'after' => 'Te pide reasignar al dar de baja a un jefe',
                ],
                [
                    'before' => 'Aprobaciones que llegan a la persona equivocada',
                    'after' => 'Cada solicitud llega al jefe directo',
                ],
                [
                    'before' => 'Capturar a la gente una y otra vez',
                    'after' => 'Una sola plantilla para todas las herramientas',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Una estructura confiable',
                    'text' => 'Carga, corrige y exporta la línea de reporte de toda la empresa.',
                ],
                [
                    'who' => 'Jefes',
                    'title' => 'Ven y aprueban a su gente',
                    'text' => 'Solo a quienes les reportan, desde su portal.',
                ],
                [
                    'who' => 'Dirección',
                    'title' => 'La empresa de un vistazo',
                    'text' => 'Cuántas personas tiene cada área y cada equipo.',
                ],
            ],
            'privacy' => null,
            'faqs' => [
                [
                    'question' => '¿Cómo hago el organigrama de mi empresa?',
                    'answer' => 'Carga a tu gente con su jefe directo, a mano o desde un CSV, y Humetría lo dibuja a partir de quién reporta a quién.',
                ],
                [
                    'question' => '¿Cuántos niveles puede tener?',
                    'answer' => 'Los que tenga tu empresa: no hay límite.',
                ],
                [
                    'question' => '¿Qué pasa cuando un jefe deja la empresa?',
                    'answer' => 'Al darlo de baja, Humetría te pide a quién reasignar a su equipo. Su historia se conserva.',
                ],
                [
                    'question' => '¿Qué ve cada jefe?',
                    'answer' => 'En su portal ve solo a quienes le reportan y aprueba sus solicitudes.',
                ],
                [
                    'question' => '¿Puedo sacar el organigrama a Excel?',
                    'answer' => 'Sí, en un CSV con nombre, puesto, área, centro, jefe directo y número de directos.',
                ],
            ],
            'related' => [
                [
                    'key' => 'leave',
                    'why' => 'Cada solicitud llega a su jefe directo.',
                ],
                [
                    'key' => 'engagement',
                    'why' => 'Resultados por jefe, con grupos de 5 o más.',
                ],
                [
                    'key' => 'attendance',
                    'why' => 'Cada jefe ve la jornada de su gente.',
                ],
            ],
            'film' => [
                'name' => 'humetria-org-chart',
                'title' => 'Organigrama en Humetría',
                'description' => 'RH abre el organigrama, que se dibuja a partir de quién reporta a quién, importa la línea de reporte desde el archivo de personal, da de baja a un jefe y reasigna a su equipo, y el jefe ve en su portal solo a quienes le reportan y aprueba sus solicitudes.',
                'duration' => 26,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
