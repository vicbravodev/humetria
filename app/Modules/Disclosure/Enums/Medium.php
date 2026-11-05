<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Enums;

/**
 * How information was put in front of the people outside the page: what the norm names as evidence of a disclosure
 * («folletos, boletines, y/o carteles», num. 10.2) and a meeting, which is how many centers really do it.
 */
enum Medium: string
{
    case Poster = 'poster';
    case Bulletin = 'bulletin';
    case Brochure = 'brochure';
    case Meeting = 'meeting';
    case Other = 'other';
    /** Paper signatures of the policy, for who has no email: how many signed, and the scanned sheet (decisión A11). */
    case SignedList = 'signed_list';

    public function label(): string
    {
        return match ($this) {
            self::Poster => 'Cartel',
            self::Bulletin => 'Boletín',
            self::Brochure => 'Folleto',
            self::Meeting => 'Reunión',
            self::Other => 'Otro medio',
            self::SignedList => 'Lista de firmas',
        };
    }
}
