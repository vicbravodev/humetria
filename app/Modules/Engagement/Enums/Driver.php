<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** The ten drivers of the company block. The value is the prefix of the key of its questions. */
enum Driver: string
{
    case Clarity = 'clarity';
    case Leadership = 'leadership';
    case Communication = 'communication';
    case Recognition = 'recognition';
    case Growth = 'growth';
    case Autonomy = 'autonomy';
    case Resources = 'resources';
    case Team = 'team';
    case Voice = 'voice';
    case Reward = 'reward';

    public function label(): string
    {
        return match ($this) {
            self::Clarity => 'Claridad y alineación',
            self::Leadership => 'Confianza en la dirección',
            self::Communication => 'Comunicación y cambios',
            self::Recognition => 'Reconocimiento',
            self::Growth => 'Crecimiento',
            self::Autonomy => 'Autonomía',
            self::Resources => 'Recursos y procesos',
            self::Team => 'Trabajo en equipo',
            self::Voice => 'Voz y trato justo',
            self::Reward => 'Compensación y prestaciones',
        };
    }

    /** A driver of two questions is reported question by question until validation confirms it as a dimension. */
    public function descriptive(): bool
    {
        return match ($this) {
            self::Recognition, self::Growth, self::Autonomy, self::Team => true,
            default => false,
        };
    }
}
