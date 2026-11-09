<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR of an address, drawn on the server as an SVG data URI (the pages allow data images), as the posters of Disclosure
 * and Complaints draw theirs: black on white with its quiet zone, whatever the theme of the screen.
 */
final class QrImage
{
    private const int SIZE = 640;

    private const int MARGIN = 4;

    public static function dataUri(string $text): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd)))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
