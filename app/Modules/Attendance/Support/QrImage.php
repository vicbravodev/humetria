<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR of an address, drawn on the server as an SVG data URI (the pages allow data images), always on white with its
 * quiet zone of 4 modules, whatever the theme of the screen. Plain (black square modules) unless `attendance.qr.branded`
 * asks for Humetría's: rounded modules in the brand ink, level H and the arch of the logo at the center, on a fifth of the
 * width (hallazgo 28 of docs/ui-polish-workers.md; off until it is scanned with a low-end Android).
 */
final class QrImage
{
    private const int SIZE = 640;

    private const int MARGIN = 4;

    /** A fifth of the width, at the center. */
    private const int MARK = 128;

    public static function dataUri(string $text): string
    {
        $svg = config()->boolean('attendance.qr.branded') ? self::branded($text) : self::plain($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private static function plain(string $text): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd)))->writeString($text);
    }

    private static function branded(string $text): string
    {
        $style = new RendererStyle(
            self::SIZE,
            self::MARGIN,
            new RoundnessModule(0.75),
            null,
            Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0x14, 0x21, 0x1F)),
        );
        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd)))
            ->writeString($text, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::H());
        $at = (self::SIZE - self::MARK) / 2;
        $mark = '<g data-mark="humetria" transform="translate('.$at.' '.$at.')">'
            .'<rect width="'.self::MARK.'" height="'.self::MARK.'" rx="30" fill="#ffffff"/>'
            .'<svg x="20" y="33" width="88" height="62" viewBox="0 5 64 46">'
            .'<path d="M10 49V33A22 22 0 0 1 54 33V49" fill="none" stroke="#0b7a6f" stroke-width="9" stroke-linecap="round"/>'
            .'</svg></g>';

        return str_replace('</svg>', $mark.'</svg>', $svg);
    }
}
