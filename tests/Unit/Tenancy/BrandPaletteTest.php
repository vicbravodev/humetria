<?php

declare(strict_types=1);

use App\Modules\Tenancy\Data\BrandPalette;

function brandLuminance(string $hex): float
{
    $channels = array_map(function (string $pair): float {
        $value = hexdec($pair) / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split(ltrim($hex, '#'), 2));

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function brandRatio(string $a, string $b): float
{
    [$high, $low] = [max(brandLuminance($a), brandLuminance($b)), min(brandLuminance($a), brandLuminance($b))];

    return ($high + 0.05) / ($low + 0.05);
}

it('keeps a color that already reads on white and on the dark background', function (): void {
    $palette = BrandPalette::from('#0b7a6f');

    expect($palette->light)->toBe('#0b7a6f');
});

it('always reads at 4.5:1 or more, in light and in dark, whatever color the company picks', function (string $hex): void {
    $palette = BrandPalette::from($hex);

    expect(brandRatio($palette->light, '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(brandRatio($palette->light, $palette->lightForeground))->toBeGreaterThanOrEqual(4.5)
        ->and(brandRatio($palette->dark, '#0f1716'))->toBeGreaterThanOrEqual(4.5)
        ->and(brandRatio($palette->dark, $palette->darkForeground))->toBeGreaterThanOrEqual(4.5)
        // The accent as text on its own soft background (the initials of the company, the badges, the chips).
        ->and(brandRatio($palette->light, $palette->lightSoft))->toBeGreaterThanOrEqual(4.5)
        ->and(brandRatio($palette->dark, $palette->darkSoft))->toBeGreaterThanOrEqual(4.5);
})->with(['#ffff00', '#ffffff', '#000000', '#1a237e', '#e91e63', '#ff6f00', '#00bcd4']);

it('gives the soft accent background a readable text on top', function (): void {
    $palette = BrandPalette::from('#e91e63');

    expect(brandRatio($palette->lightSoft, '#14211f'))->toBeGreaterThanOrEqual(4.5)
        ->and(brandRatio($palette->darkSoft, '#f1faf8'))->toBeGreaterThanOrEqual(4.5);
});

it('writes the variables the design system reads, for light and for dark', function (): void {
    $css = BrandPalette::from('#e91e63')->css();

    expect($css)->toContain(':root{')->toContain('.dark{')
        ->toContain('--primary:')->toContain('--primary-foreground:')->toContain('--accent:')->toContain('--ring:')
        // The sidebar and the charts have tokens of their own: left alone, they stay Humetría's green.
        ->toContain('--sidebar-primary:')->toContain('--sidebar-primary-foreground:')->toContain('--sidebar-accent:')->toContain('--sidebar-ring:')->toContain('--chart-1:');
});

it('refuses what is not a six-digit hex color', function (string $value): void {
    BrandPalette::from($value);
})->with(['red', '#fff', '#12345g', ''])->throws(InvalidArgumentException::class);
