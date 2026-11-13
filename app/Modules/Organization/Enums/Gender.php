<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

use Illuminate\Support\Str;

/**
 * An employee's gender, as the company records it. NOM-035's Guía III draws its sample proportionally by gender.
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    /** The gender as a spreadsheet may write it ("Mujer", "hombre", "F", "Otro"…); null when it is none of them. «M» is ambiguous (Masculino or Mujer) and is not read. */
    public static function fromSpanish(string $text): ?self
    {
        return match (Str::of($text)->ascii()->lower()->trim()->toString()) {
            'mujer', 'f', 'femenino' => self::Female,
            'hombre', 'h', 'masculino' => self::Male,
            'otro', 'o', 'otro / prefiero no decir', 'prefiero no decir' => self::Other,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Mujer',
            self::Male => 'Hombre',
            self::Other => 'Otro / prefiero no decir',
        };
    }
}
