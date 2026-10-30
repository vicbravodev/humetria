<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * The roster changed since the draft was prepared, so it can no longer be launched as it is.
 */
final class DraftOutdated
{
    public static function guides(): ValidationException
    {
        return ValidationException::withMessages(['campaign' => 'La plantilla cambió desde que preparaste el borrador: ahora aplican otras guías o otra muestra. Elimina el borrador y prepara uno nuevo.']);
    }

    public static function sample(): ValidationException
    {
        return ValidationException::withMessages(['campaign' => 'La plantilla cambió desde que sorteaste la muestra. Vuelve a sortearla antes de lanzar.']);
    }
}
