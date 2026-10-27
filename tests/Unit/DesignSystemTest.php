<?php

declare(strict_types=1);

/** @return array<string, string> token => #hex */
function themeTokens(string $selector): array
{
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

    if (preg_match('/^'.preg_quote($selector, '/').'\s*\{(.*?)^\}/ms', $css, $block) !== 1) {
        return [];
    }

    preg_match_all('/--([\w-]+):\s*(#[0-9A-Fa-f]{6})/', $block[1], $matches);

    return array_combine($matches[1], $matches[2]);
}

function luminance(string $hex): float
{
    return array_sum(array_map(
        static function (int $channel, float $weight): float {
            $c = $channel / 255;

            return $weight * ($c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
        },
        array_map('hexdec', str_split(ltrim($hex, '#'), 2)),
        [0.2126, 0.7152, 0.0722],
    ));
}

function contrastRatio(string $hexA, string $hexB): float
{
    [$light, $dark] = [max(luminance($hexA), luminance($hexB)), min(luminance($hexA), luminance($hexB))];

    return ($light + 0.05) / ($dark + 0.05);
}

dataset('themes', [':root', '.dark']);

it('defines the same 49 color tokens in light and dark', function (): void {
    expect(array_keys(themeTokens(':root')))->toHaveCount(49)
        ->toEqualCanonicalizing(array_keys(themeTokens('.dark')));
});

it('keeps text at 4.5:1 or more', function (string $theme, string $text, string $surface): void {
    $tokens = themeTokens($theme);
    expect(contrastRatio($tokens[$text], $tokens[$surface]))->toBeGreaterThanOrEqual(4.5);
})->with('themes')->with([
    ['foreground', 'background'], ['card-foreground', 'card'], ['popover-foreground', 'popover'],
    ['primary', 'background'], ['primary', 'card'], ['primary-foreground', 'primary'],
    ['secondary-foreground', 'secondary'], ['muted-foreground', 'background'], ['muted-foreground', 'card'],
    ['muted-foreground', 'muted'], ['accent-foreground', 'accent'], ['destructive', 'card'],
    ['destructive', 'background'], ['on-destructive', 'destructive'], ['sidebar-foreground', 'sidebar'],
    ['sidebar-primary-foreground', 'sidebar-primary'], ['sidebar-accent-foreground', 'sidebar-accent'],
    ['risk-nulo-fg', 'risk-nulo-bg'], ['risk-bajo-fg', 'risk-bajo-bg'], ['risk-medio-fg', 'risk-medio-bg'],
    ['risk-alto-fg', 'risk-alto-bg'], ['risk-muy-alto-fg', 'risk-muy-alto-bg'],
]);

it('keeps control borders, focus and data marks at 3:1 or more', function (string $theme, string $mark, string $surface): void {
    $tokens = themeTokens($theme);
    expect(contrastRatio($tokens[$mark], $tokens[$surface]))->toBeGreaterThanOrEqual(3.0);
})->with('themes')->with([
    ['input', 'background'], ['input', 'card'], ['ring', 'background'], ['ring', 'card'],
    ['sidebar-ring', 'sidebar'], ['sidebar-primary', 'sidebar'],
    ['chart-1', 'card'], ['chart-2', 'card'], ['chart-3', 'card'], ['chart-4', 'card'], ['chart-5', 'card'],
    ['risk-nulo', 'card'], ['risk-bajo', 'card'], ['risk-medio', 'card'], ['risk-alto', 'card'], ['risk-muy-alto', 'card'],
]);

it('measures contrast like WCAG', function (): void {
    expect(round(contrastRatio('#0B7A6F', '#FFFFFF'), 2))->toBe(5.21)
        ->and(round(contrastRatio('#FFFFFF', '#000000'), 2))->toBe(21.0);
});
