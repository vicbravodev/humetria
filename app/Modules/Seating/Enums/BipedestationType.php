<?php

declare(strict_types=1);

namespace App\Modules\Seating\Enums;

/**
 * The types of bipedestación of the Disposiciones (Cuarta and Quinta C IX): static, dynamic, prolonged or a mix.
 */
enum BipedestationType: string
{
    case Static = 'static';
    case Dynamic = 'dynamic';
    case Prolonged = 'prolonged';
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::Static => 'Estática',
            self::Dynamic => 'Dinámica',
            self::Prolonged => 'Prolongada',
            self::Combined => 'Combinación',
        };
    }
}
