<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use GdImage;

/**
 * Where the mark of a logo is inside its image: the box left once the empty margins are gone, so the logo fills the space it
 * is given instead of floating in it. The background is what the top-left corner is: transparent, or a plain color (a white
 * JPG). A logo with no mark to find, or one that already reaches its edges, comes back whole.
 */
final class LogoBounds
{
    /** The longest side of the copy the mark is searched in: the box is found on a small copy, so a 4000 px logo costs no more. */
    private const int PROBE = 400; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    /** How far a channel may differ from the plain background and still be background (JPG noise, soft edges). */
    private const int TOLERANCE = 32; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    /** Alpha (0 opaque, 127 clear) from which a pixel counts as empty. */
    private const int CLEAR = 110; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    /** Breathing room around the mark, as a share of its longest side. */
    private const float PADDING = 0.03; // @pest-mutate-ignore: IncrementFloat,DecrementFloat

    /**
     * @return array{x: int, y: int, width: int, height: int}
     */
    public static function of(GdImage $image): array
    {
        $whole = ['x' => 0, 'y' => 0, 'width' => imagesx($image), 'height' => imagesy($image)];
        $probe = imagescale($image, min(self::PROBE, imagesx($image)), -1, IMG_BILINEAR_FIXED);

        // GD answers false only when it fails inside (out of memory) or for a point outside the image, which none of the three
        // checks of false can reach: the logo is at least 40 px high and at most 4000 wide (UpdateTenantLogoRequest), so the probe
        // is never under one pixel, and every point read is inside it.
        if ($probe === false) {
            return $whole; // @pest-mutate-ignore: RemoveEarlyReturn
        }

        $box = self::box($probe);

        if ($box === null) {
            return $whole;
        }

        $scale = imagesx($image) / imagesx($probe);
        $pad = (int) round(max($box['right'] - $box['left'], $box['bottom'] - $box['top']) * self::PADDING);
        $left = max(0, $box['left'] - $pad);
        $top = max(0, $box['top'] - $pad);
        $right = min(imagesx($probe) - 1, $box['right'] + $pad);
        $bottom = min(imagesy($probe) - 1, $box['bottom'] + $pad);

        return [
            'x' => (int) floor($left * $scale),
            'y' => (int) floor($top * $scale),
            'width' => min(imagesx($image), (int) ceil(($right - $left + 1) * $scale)),
            'height' => min(imagesy($image), (int) ceil(($bottom - $top + 1) * $scale)),
        ];
    }

    /**
     * @return array{left: int, top: int, right: int, bottom: int}|null null when there is no mark to find
     */
    private static function box(GdImage $probe): ?array
    {
        $width = imagesx($probe);
        $height = imagesy($probe);
        $corner = imagecolorat($probe, 0, 0);

        if ($corner === false) {
            return null; // @pest-mutate-ignore: RemoveEarlyReturn
        }

        // A transparent corner: the background is nothing. Otherwise it is the corner's own color.
        $plain = (($corner >> 24) & 127) >= self::CLEAR ? null : $corner;
        $left = $width;
        $top = $height;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! self::isMark(imagecolorat($probe, $x, $y), $plain)) {
                    continue;
                }

                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = $y;
            }
        }

        return $right < 0 ? null : ['left' => $left, 'top' => $top, 'right' => $right, 'bottom' => $bottom];
    }

    private static function isMark(int|false $pixel, ?int $plain): bool
    {
        if ($pixel === false) {
            return false; // @pest-mutate-ignore: FalseToTrue,RemoveEarlyReturn
        }

        if ((($pixel >> 24) & 127) >= self::CLEAR) {
            return false;
        }

        if ($plain === null) {
            return true;
        }

        return abs((($pixel >> 16) & 255) - (($plain >> 16) & 255)) > self::TOLERANCE
            || abs((($pixel >> 8) & 255) - (($plain >> 8) & 255)) > self::TOLERANCE
            || abs(($pixel & 255) - ($plain & 255)) > self::TOLERANCE;
    }
}
