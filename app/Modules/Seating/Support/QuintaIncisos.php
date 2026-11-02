<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

/**
 * The incisos of the fifth provision of the Disposiciones (Quinta A to J) a document can cover, with a short name each.
 */
final class QuintaIncisos
{
    public const array LABELS = [
        'A' => 'Análisis con la Figura 1',
        'B' => 'Registro en las actas de recorrido',
        'C' => 'Contenido del registro',
        'D' => 'Nivel de riesgo de cada persona',
        'E' => 'Tipo de asiento',
        'F' => 'Características del asiento',
        'G' => 'Medidas preventivas',
        'H' => 'Información a las personas',
        'I' => 'Señalización',
        'J' => 'Canalización a atención médica',
    ];
}
