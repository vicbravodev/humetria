<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the organigrama (/organigrama). No law asks for it, so it cites none.
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
            'h1' => 'El organigrama de tu empresa, dibujado a partir de quién reporta a quién',
            'lead' => 'Carga la línea de reporte desde tu archivo de personal y el organigrama se dibuja solo, sin límite de niveles. Cuando un jefe se va, te pide reasignar a su equipo, y cada jefe ve en su portal solo a quienes le reportan.',
            'norm' => null,
            'features' => [
                ['title' => 'Se dibuja solo', 'text' => 'El organigrama sale de quién reporta a quién: no lo dibujas a mano ni lo actualizas por separado. Sirve para empresas de cualquier tamaño, sin límite de niveles.'],
                ['title' => 'Desde tu archivo de personal', 'text' => 'Importa a tu gente con su jefe directo desde un archivo CSV, y antes de guardar ves qué se va a crear y qué se va a cambiar.'],
                ['title' => 'Nadie queda sin jefe', 'text' => 'Al dar de baja a un jefe, te pide reasignar a su equipo. La baja no borra a nadie: la historia de cada persona se conserva.'],
                ['title' => 'Cada jefe, solo su gente', 'text' => 'El jefe ve en su portal solo a quienes le reportan y aprueba sus solicitudes, como las de vacaciones y permisos.'],
                ['title' => 'Para abrir en una hoja de cálculo', 'text' => 'Exporta la línea de reporte con nombre, puesto, área, centro de trabajo, jefe directo y número de directos, sin datos de contacto.'],
            ],
            'audience' => [
                'company' => 'RH mantiene la plantilla, las áreas y los niveles de puesto en un solo lugar, y el organigrama, las aprobaciones y las demás herramientas leen de ahí.',
                'consultant' => 'Si asesoras a varias empresas, llevas el organigrama de cada una por separado desde la misma cuenta.',
            ],
            'privacy' => 'Cada jefe ve solo a quienes le reportan, y la exportación del organigrama no lleva datos de contacto.',
            'faqs' => [
                ['question' => '¿Cómo hago el organigrama de mi empresa?', 'answer' => 'Carga a tu gente con su jefe directo, a mano o desde un archivo CSV, y Humetría dibuja el organigrama a partir de quién reporta a quién.'],
                ['question' => '¿Cuántos niveles puede tener?', 'answer' => 'Los que tenga tu empresa: no hay límite de niveles.'],
                ['question' => '¿Qué pasa cuando un jefe deja la empresa?', 'answer' => 'Al darlo de baja, Humetría te pide a quién reasignar a su equipo, para que nadie quede bajo alguien que ya se fue. Su historia se conserva.'],
                ['question' => '¿Qué ve cada jefe?', 'answer' => 'En su portal ve solo a quienes le reportan y aprueba sus solicitudes. No ve al resto de la empresa.'],
                ['question' => '¿Puedo sacar el organigrama a Excel?', 'answer' => 'Sí. Exportas la línea de reporte en un CSV que abre cualquier hoja de cálculo, con nombre, puesto, área, centro de trabajo, jefe directo y número de directos.'],
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
