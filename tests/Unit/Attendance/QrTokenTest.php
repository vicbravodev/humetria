<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\QrToken;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

mutates(QrToken::class);

const QR_TENANT = '0b6c3d3e-9a51-4f8a-9a0f-2f1d6e3b8c11';
const QR_CENTER = '7f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e4f';

// 12:00:00 opens a 30 s window (43200 is a multiple of 30).
function qrAt(int $seconds): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-07 12:00:00', 'UTC')->addSeconds($seconds);
}

it('is the HMAC-SHA256 with the app key of its own domain, the company, the center and the 30 s window', function (): void {
    $window = intdiv(qrAt(0)->getTimestamp(), 30);
    $expected = hash_hmac('sha256', 'attendance-qr|'.QR_TENANT.'|'.QR_CENTER.'|'.$window, config()->string('app.key'));

    expect(QrToken::issue(QR_TENANT, QR_CENTER, qrAt(0)))->toBe($expected)
        ->and(QrToken::issue(QR_TENANT, QR_CENTER, qrAt(29)))->toBe($expected)
        ->and(QrToken::issue(QR_TENANT, QR_CENTER, qrAt(30)))->not->toBe($expected);
});

it('verifies a code of the current window and of the one before', function (int $issuedAt, int $now): void {
    expect(QrToken::verify(QrToken::issue(QR_TENANT, QR_CENTER, qrAt($issuedAt)), QR_TENANT, QR_CENTER, qrAt($now)))->toBeTrue();
})->with([
    'now' => [0, 0],
    '29 s ago, same window' => [0, 29],
    '29 s ago, previous window' => [29, 58],
    'end of the previous window' => [0, 59],
]);

it('rejects a code older than the previous window, or from the future', function (int $issuedAt, int $now): void {
    expect(QrToken::verify(QrToken::issue(QR_TENANT, QR_CENTER, qrAt($issuedAt)), QR_TENANT, QR_CENTER, qrAt($now)))->toBeFalse();
})->with([
    '60 s, two windows back' => [0, 60],
    '61 s ago' => [0, 61],
    'next window' => [30, 0],
]);

it('rejects a code of another center or another company', function (): void {
    $token = QrToken::issue(QR_TENANT, QR_CENTER, qrAt(0));

    expect(QrToken::verify($token, QR_TENANT, '11111111-2222-4333-8444-555555555555', qrAt(0)))->toBeFalse()
        ->and(QrToken::verify($token, '11111111-2222-4333-8444-555555555555', QR_CENTER, qrAt(0)))->toBeFalse();
});

it('rejects a code with one character changed, cut or empty', function (): void {
    $token = QrToken::issue(QR_TENANT, QR_CENTER, qrAt(0));
    $tampered = substr($token, 0, -1).($token[-1] === 'a' ? 'b' : 'a');

    expect(QrToken::verify($tampered, QR_TENANT, QR_CENTER, qrAt(0)))->toBeFalse()
        ->and(QrToken::verify(substr($token, 0, -1), QR_TENANT, QR_CENTER, qrAt(0)))->toBeFalse()
        ->and(QrToken::verify('', QR_TENANT, QR_CENTER, qrAt(0)))->toBeFalse();
});

it('expires at the end of the window after its own', function (): void {
    expect(QrToken::expiresAt(qrAt(0)))->toEqual(qrAt(30))
        ->and(QrToken::expiresAt(qrAt(29)))->toEqual(qrAt(30))
        ->and(QrToken::expiresAt(qrAt(30)))->toEqual(qrAt(60));
});

it('takes its window and how many past windows it accepts from config/attendance.php', function (): void {
    config(['attendance.qr.window_seconds' => 60, 'attendance.qr.past_windows' => 2]);
    $token = QrToken::issue(QR_TENANT, QR_CENTER, qrAt(0));

    expect(QrToken::expiresAt(qrAt(0)))->toEqual(qrAt(60))
        ->and(QrToken::verify($token, QR_TENANT, QR_CENTER, qrAt(179)))->toBeTrue()
        ->and(QrToken::verify($token, QR_TENANT, QR_CENTER, qrAt(180)))->toBeFalse();
});

it('defaults to 30 s windows and one past window', function (): void {
    expect(config('attendance.qr'))->toBe(['window_seconds' => 30, 'past_windows' => 1]);
});
