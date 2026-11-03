<?php

declare(strict_types=1);

namespace App\Modules\Organization\Enums;

use Illuminate\Support\Str;

/**
 * Whether a person is covered by the collective contract (unionized) or is a trusted employee.
 */
enum StaffGroup: string
{
    case Unionized = 'unionized';
    case Trusted = 'trusted';

    /** The group as a spreadsheet may write it ("Sindicalizado", "sindicalizada", "de confianza"…); null when it is none. */
    public static function fromSpanish(string $text): ?self
    {
        return match (Str::of($text)->ascii()->lower()->trim()->toString()) {
            'sindicalizado', 'sindicalizada', 'sindicalizados', 'sindicalizadas' => self::Unionized,
            'confianza', 'de confianza' => self::Trusted,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Unionized => 'Sindicalizado',
            self::Trusted => 'Confianza',
        };
    }
}
