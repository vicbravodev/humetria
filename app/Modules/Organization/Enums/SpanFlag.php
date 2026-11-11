<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/**
 * A number of direct reports worth a second look: a signal, never a rule.
 */
enum SpanFlag: string
{
    case Few = 'few';
    case Many = 'many';

    public function label(): string
    {
        return match ($this) {
            self::Few => 'Revisar: pocos reportes',
            self::Many => 'Revisar: muchos reportes',
        };
    }
}
