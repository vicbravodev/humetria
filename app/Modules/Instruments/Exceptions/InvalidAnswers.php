<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Exceptions;

use InvalidArgumentException;

/**
 * A questionnaire that is not complete or not coherent with its definition. Its message is for the person answering.
 */
final class InvalidAnswers extends InvalidArgumentException
{
    public static function incomplete(): self
    {
        return new self('Conteste todas las preguntas.');
    }

    public static function unexpected(): self
    {
        return new self('Las respuestas no corresponden a este cuestionario. Recargue la página e intente de nuevo.');
    }
}
