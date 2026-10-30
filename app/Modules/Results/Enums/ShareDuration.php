<?php

declare(strict_types=1);

namespace App\Modules\Results\Enums;

/**
 * How long a link to a report lasts: from 15 minutes to 48 hours, the longest the database accepts.
 */
enum ShareDuration: string
{
    case FifteenMinutes = '15m';
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case OneDay = '24h';
    case ThirtySixHours = '36h';
    case TwoDays = '48h';

    public function minutes(): int
    {
        return match ($this) {
            self::FifteenMinutes => 15,
            self::ThirtyMinutes => 30,
            self::OneHour => 60,
            self::OneDay => 1440,
            self::ThirtySixHours => 2160,
            self::TwoDays => 2880,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FifteenMinutes => '15 minutos',
            self::ThirtyMinutes => '30 minutos',
            self::OneHour => '1 hora',
            self::OneDay => '24 horas',
            self::ThirtySixHours => '36 horas',
            self::TwoDays => '48 horas',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $duration): array => ['value' => $duration->value, 'label' => $duration->label()], self::cases());
    }
}
