<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

use InvalidArgumentException;

/**
 * The accent of a company, made safe to read: the color it picked is nudged, only as much as needed, until it reads at
 * 4.5:1 on the light page and on the dark one (design system: text >= 4.5:1), and the text on top of it, and on its soft
 * background, does too. Whatever a company picks, the contrast the design system validated holds.
 */
final readonly class BrandPalette
{
    private const string LIGHT_PAGE = '#ffffff';

    private const string DARK_PAGE = '#0f1716';

    private const string LIGHT_TEXT = '#14211f';

    private const string DARK_TEXT = '#f1faf8';

    private function __construct(
        public string $light,
        public string $lightForeground,
        public string $lightSoft,
        public string $dark,
        public string $darkForeground,
        public string $darkSoft,
    ) {}

    public static function from(string $hex): self
    {
        if (preg_match('/^#[0-9a-f]{6}$/i', $hex) !== 1) {
            throw new InvalidArgumentException('The accent is not a six-digit hex color.');
        }

        $hex = strtolower($hex);
        // The accent is also text on its own soft background (initials, badges): it has to read on the tint, the stricter of the two.
        $lightTint = self::mix($hex, self::LIGHT_PAGE, 0.9);
        $darkTint = self::mix($hex, self::DARK_PAGE, 0.78);

        return new self(
            self::reach($hex, $lightTint, '#000000'),
            self::LIGHT_PAGE,
            self::reach($lightTint, self::LIGHT_TEXT, '#ffffff'),
            self::reach($hex, $darkTint, '#ffffff'),
            self::DARK_PAGE,
            self::reach($darkTint, self::DARK_TEXT, '#000000'),
        );
    }

    /** The variables the design system reads, for the light page and the dark one. */
    public function css(): string
    {
        return ':root{'.self::variables($this->light, $this->lightForeground, $this->lightSoft, self::LIGHT_TEXT).'}'
            .'.dark{'.self::variables($this->dark, $this->darkForeground, $this->darkSoft, self::DARK_TEXT).'}';
    }

    private static function variables(string $primary, string $foreground, string $soft, string $softText): string
    {
        return "--primary:{$primary};--primary-foreground:{$foreground};--ring:{$primary};--accent:{$soft};--accent-foreground:{$softText};";
    }

    /** Moves the color toward $toward in 4 % steps until it reads at 4.5:1 against $against. */
    private static function reach(string $hex, string $against, string $toward): string
    {
        for ($step = 0.0; $step <= 1.0; $step += 0.04) {
            $candidate = self::mix($hex, $toward, $step);

            if (self::contrast($candidate, $against) >= 4.5) {
                return $candidate;
            }
        }

        return $toward;
    }

    /** $amount of $b over $a. */
    private static function mix(string $a, string $b, float $amount): string
    {
        $channels = array_map(
            fn (int $from, int $to): string => str_pad(dechex((int) round($from + ($to - $from) * $amount)), 2, '0', STR_PAD_LEFT),
            self::channels($a),
            self::channels($b),
        );

        return '#'.implode('', $channels);
    }

    /** @return list<int> */
    private static function channels(string $hex): array
    {
        return array_map(fn (string $pair): int => intval($pair, 16), str_split(ltrim($hex, '#'), 2));
    }

    private static function contrast(string $a, string $b): float
    {
        $high = max(self::luminance($a), self::luminance($b));
        $low = min(self::luminance($a), self::luminance($b));

        return ($high + 0.05) / ($low + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::channels($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
