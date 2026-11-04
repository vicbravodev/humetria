<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR of an address, drawn on the server as an SVG and handed to the page as a data URI (the policy of the pages allows data
 * images), so no service outside ever sees the address of the page of the workers.
 */
final readonly class QrCode
{
    private const int SIZE = 480;

    private const int MARGIN = 2;

    public function dataUri(string $text): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd)))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
