<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Enums;

use Carbon\CarbonImmutable;

/** How long someone has worked in the company, in the coarse ranges a result may be cut by (never the exact date). */
enum TenureBand: string
{
    case UnderOne = 'lt1';
    case OneToThree = '1to3';
    case ThreeToSix = '3to6';
    case SixPlus = '6plus';

    /** The range of someone who started on `$start`, on `$day`, by years completed. */
    public static function on(CarbonImmutable $start, CarbonImmutable $day): self
    {
        return match (true) {
            $start->addYears(1)->greaterThan($day) => self::UnderOne,
            $start->addYears(3)->greaterThan($day) => self::OneToThree,
            $start->addYears(6)->greaterThan($day) => self::ThreeToSix,
            default => self::SixPlus,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UnderOne => 'Menos de 1 año',
            self::OneToThree => 'De 1 a 3 años',
            self::ThreeToSix => 'De 3 a 6 años',
            self::SixPlus => '6 años o más',
        };
    }
}
