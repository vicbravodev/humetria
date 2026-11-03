<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The nine characteristics of a seat or chair of the Disposiciones (Quinta F), each with the DOF's words for what it gives.
 */
enum SeatFeature: string
{
    case Backrest = 'backrest';
    case Height = 'height';
    case LumbarSupport = 'lumbar_support';
    case Adjustable = 'adjustable';
    case Ergonomic = 'ergonomic';
    case Mobility = 'mobility';
    case Stability = 'stability';
    case Armrests = 'armrests';
    case Footrest = 'footrest';

    public function label(): string
    {
        return match ($this) {
            self::Backrest => 'Respaldo',
            self::Height => 'Altura del asiento',
            self::LumbarSupport => 'Soporte lumbar',
            self::Adjustable => 'Capacidad de ajuste',
            self::Ergonomic => 'Ergonomía',
            self::Mobility => 'Facilidad de movimiento',
            self::Stability => 'Estabilidad',
            self::Armrests => 'Reposabrazos',
            self::Footrest => 'Reposapiés',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Backrest => 'Proporciona soporte evitando la fatiga muscular.',
            self::Height => 'Permite que los pies toquen el suelo sin tensión en piernas ni espalda.',
            self::LumbarSupport => 'Se adapta a la curvatura natural de la columna con apoyo firme pero flexible.',
            self::Adjustable => 'Permite acomodar altura, profundidad, inclinación del respaldo y reposabrazos.',
            self::Ergonomic => 'Promueve una postura saludable y reduce la presión en espalda, hombros, cuello y piernas.',
            self::Mobility => 'Tiene ruedas o mecanismos de giro para moverse sin levantarse.',
            self::Stability => 'Evita que se mueva o incline involuntariamente.',
            self::Armrests => 'Apoya los antebrazos para reducir la tensión en hombros.',
            self::Footrest => 'Se extiende o retrae para elevar o bajar las piernas.',
        };
    }
}
