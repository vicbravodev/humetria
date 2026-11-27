<?php

declare(strict_types=1);

namespace App\Modules\Demos\Enums;

/** A tour of a guided session: a module the demo can show. «base» (the center and its people) always goes first, unchosen. */
enum TourKey: string
{
    case Base = 'base';
    case Nom035 = 'nom035';
    case Attendance = 'attendance';
    case Engagement = 'engagement';
    case Complaints = 'complaints';
    case Leave = 'leave';
    case Humi = 'humi';

    /** How the guide and the room name the tour. */
    public function title(): string
    {
        return match ($this) {
            self::Base => 'El centro y su gente',
            self::Nom035 => 'NOM-035',
            self::Attendance => 'Asistencia',
            self::Engagement => 'Clima y pulso',
            self::Complaints => 'Denuncias',
            self::Leave => 'Vacaciones',
            self::Humi => 'Humi',
        };
    }

    /** What the tour shows, in one sentence, for the room where the presenter picks the tours. */
    public function summary(): string
    {
        return match ($this) {
            self::Base => 'Siempre primero: das de alta a tu público con su correo y teléfono.',
            self::Nom035 => 'Crea la evaluación, el público contesta en su teléfono y ves el informe al cerrar.',
            self::Attendance => 'Modo del centro, zona y kiosco; el público crea su llave y marca.',
            self::Engagement => 'Encuesta de clima del centro; las respuestas llegan en vivo.',
            self::Complaints => 'Alguien denuncia con el QR y RH la recibe y la atiende.',
            self::Leave => 'Abres el portal de una persona, pide días y los apruebas como RH.',
            self::Humi => 'Una pregunta sobre el cumplimiento de la empresa.',
        };
    }

    /** About how long the tour takes in front of the client, in minutes. */
    public function minutes(): int
    {
        return match ($this) {
            self::Base => 4,
            self::Nom035 => 12,
            self::Attendance => 10,
            self::Engagement => 8,
            self::Complaints => 5,
            self::Leave => 6,
            self::Humi => 3,
        };
    }
}
