<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** The QR of an address, drawn on the server as an SVG data URI (the pages' policy allows data images): no outside service sees it. */
final readonly class QrCode
{
    public function dataUri(string $text): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(480, 2), new SvgImageBackEnd)))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
