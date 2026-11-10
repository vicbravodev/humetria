<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Exceptions;

use RuntimeException;

/** A survey cannot be launched as asked; the message is what the person who launches it reads (the controller shows it as a 422). */
final class SurveyCannotLaunch extends RuntimeException
{
    public static function tooFewPeople(int $minimum): self
    {
        return new self("Se necesitan al menos {$minimum} personas para proteger el anonimato.");
    }

    public static function badDeadline(int $maxDays): self
    {
        return new self("La fecha de cierre debe ser posterior a hoy y a lo más en {$maxDays} días.");
    }
}
