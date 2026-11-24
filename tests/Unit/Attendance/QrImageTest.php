<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\QrImage;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Tests\TestCase;

uses(TestCase::class);

mutates(QrImage::class);

const QR_IMAGE_URL = 'https://humetria.test/me/acme/punch?q=4f9c2a7e1b3d5f6a8c0e2b4d6f8a1c3e5b7d9f0a2c4e6b8d0f1a3c5e7b9d1f3a';

function qrSvg(): string
{
    return base64_decode(str(QrImage::dataUri(QR_IMAGE_URL))->after('data:image/svg+xml;base64,')->toString(), true) ?: '';
}

/** The scale of a 640 px code of `$level`, with its 4 modules of quiet zone on each side. */
function qrScale(ErrorCorrectionLevel $level): string
{
    $modules = Encoder::encode(QR_IMAGE_URL, $level, Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix()->getWidth();

    return 'scale('.round(640 / ($modules + 8), 3).')';
}

it('draws the plain code by default: black square modules on white, its quiet zone, low correction', function (): void {
    $svg = qrSvg();

    expect(config('attendance.qr.branded'))->toBeFalse()
        ->and($svg)->toContain('<rect x="0" y="0" width="640" height="640" fill="#ffffff"/>')
        ->toContain('translate(4,4)')
        ->toContain('fill="#000000"')
        ->toContain(qrScale(ErrorCorrectionLevel::L()))
        ->not->toContain('#14211f')
        ->not->toContain('data-mark');
});

it('draws the Humetría code when configured: rounded modules in the brand ink, high correction and the arch at the center', function (): void {
    config(['attendance.qr.branded' => true]);
    $svg = qrSvg();

    expect($svg)->toContain('<rect x="0" y="0" width="640" height="640" fill="#ffffff"/>')
        ->toContain('translate(4,4)')
        ->toContain('fill="#14211f"')
        ->not->toContain('#000000')
        // Rounded corners: arcs of three eighths of a module, as the board scanned by Victor.
        ->toContain('A0.375 0.375 0 0 0 ')
        ->toContain('<svg x="20" y="33" width="88" height="62" viewBox="0 5 64 46">')
        // Level H recovers 30 % of the code: a mark on 4 % of it (a fifth of the width, squared) leaves room to spare.
        ->toContain(qrScale(ErrorCorrectionLevel::H()))
        ->toContain('<g data-mark="humetria" transform="translate(256 256)">')
        ->toContain('<rect width="128" height="128" rx="30" fill="#ffffff"/>')
        ->toContain('M10 49V33A22 22 0 0 1 54 33V49')
        ->toContain('stroke="#0b7a6f"')
        ->toEndWith("</svg>\n");
});
