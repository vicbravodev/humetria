<?php

declare(strict_types=1);

use App\Modules\Messaging\Support\ResendSignature;
use Tests\TestCase;

uses(TestCase::class);

mutates(ResendSignature::class);

const RESEND_NOW = 1_700_000_000;

/** What Svix sends: HMAC-SHA256 of `{id}.{timestamp}.{body}` with the base64-decoded secret after `whsec_`, in base64. */
function svixSignature(string $secret, string $id, int $timestamp, string $body): string
{
    $key = base64_decode(substr($secret, 6), true);

    return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", (string) $key, true));
}

beforeEach(function (): void {
    $this->secret = 'whsec_'.base64_encode('the-current-secret');
    $this->previous = 'whsec_'.base64_encode('the-previous-secret');
    config(['services.resend.webhook_secrets' => [$this->secret], 'services.resend.webhook_tolerance_seconds' => 300]);
});

it('accepts a signature made with the secret over the raw body', function (): void {
    $header = svixSignature($this->secret, 'msg_1', RESEND_NOW, '{"type":"email.sent"}');

    expect((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, $header, '{"type":"email.sent"}', RESEND_NOW))->toBeTrue();
});

it('rejects a body that changed by a byte', function (): void {
    $header = svixSignature($this->secret, 'msg_1', RESEND_NOW, '{"type":"email.sent"}');

    expect((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, $header, '{"type":"email.sent" }', RESEND_NOW))->toBeFalse();
});

it('rejects another id, another timestamp and another secret', function (): void {
    $header = svixSignature($this->secret, 'msg_1', RESEND_NOW, 'body');
    $other = svixSignature($this->previous, 'msg_1', RESEND_NOW, 'body');

    expect((new ResendSignature)->valid('msg_2', (string) RESEND_NOW, $header, 'body', RESEND_NOW))->toBeFalse()
        ->and((new ResendSignature)->valid('msg_1', (string) (RESEND_NOW + 1), $header, 'body', RESEND_NOW + 1))->toBeFalse()
        ->and((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, $other, 'body', RESEND_NOW))->toBeFalse();
});

it('rejects a timestamp outside the tolerance and accepts the exact limit', function (): void {
    $stamp = RESEND_NOW - 300;
    $header = svixSignature($this->secret, 'msg_1', $stamp, 'body');
    $late = RESEND_NOW - 301;
    $lateHeader = svixSignature($this->secret, 'msg_1', $late, 'body');
    $future = RESEND_NOW + 301;
    $futureHeader = svixSignature($this->secret, 'msg_1', $future, 'body');

    expect((new ResendSignature)->valid('msg_1', (string) $stamp, $header, 'body', RESEND_NOW))->toBeTrue()
        ->and((new ResendSignature)->valid('msg_1', (string) $late, $lateHeader, 'body', RESEND_NOW))->toBeFalse()
        ->and((new ResendSignature)->valid('msg_1', (string) $future, $futureHeader, 'body', RESEND_NOW))->toBeFalse();
});

it('accepts the previous secret during a rotation, and any of several signatures', function (): void {
    config(['services.resend.webhook_secrets' => [$this->secret, $this->previous]]);
    $old = svixSignature($this->previous, 'msg_1', RESEND_NOW, 'body');

    expect((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, 'v1,AAAA '.$old, 'body', RESEND_NOW))->toBeTrue();
});

it('ignores signatures that are not v1, and a timestamp that is not a number', function (): void {
    $header = svixSignature($this->secret, 'msg_1', RESEND_NOW, 'body');

    expect((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, 'v2,'.substr($header, 3), 'body', RESEND_NOW))->toBeFalse()
        ->and((new ResendSignature)->valid('msg_1', 'soon', $header, 'body', RESEND_NOW))->toBeFalse()
        ->and((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, '', 'body', RESEND_NOW))->toBeFalse();
});

it('accepts nothing when no secret is configured', function (): void {
    config(['services.resend.webhook_secrets' => []]);

    expect((new ResendSignature)->valid('msg_1', (string) RESEND_NOW, svixSignature($this->secret, 'msg_1', RESEND_NOW, 'body'), 'body', RESEND_NOW))->toBeFalse();
});
