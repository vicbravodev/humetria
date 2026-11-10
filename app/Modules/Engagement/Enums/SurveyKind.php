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

    /** About how long answering takes, as the email says it. */
    public function minutes(): int
    {
        return match ($this) {
            self::Climate => 12,
            self::Pulse => 2,
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
