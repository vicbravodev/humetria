<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

/** The two surveys the engine runs: the yearly climate survey and the short pulse that follows it. */
enum SurveyKind: string
{
    case Climate = 'climate';
    case Pulse = 'pulse';

    public function label(): string
    {
        return match ($this) {
            self::Climate => 'Clima',
            self::Pulse => 'Pulso',
        };
    }

    public function viewPermission(): string
    {
        return "view-{$this->value}";
    }

    public function managePermission(): string
    {
        return "manage-{$this->value}";
    }
}
