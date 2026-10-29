<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

/**
 * An employee's gender, as the company records it. NOM-035's Guía III draws its sample proportionally by gender.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Mujer',
            self::Male => 'Hombre',
            self::Other => 'Otro / prefiero no decir',
        };
    }
}
