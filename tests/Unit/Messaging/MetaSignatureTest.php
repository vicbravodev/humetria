<?php

declare(strict_types=1);

use App\Modules\Messaging\Support\MetaSignature;
use Tests\TestCase;

uses(TestCase::class);

mutates(MetaSignature::class);

function sign(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

beforeEach(function (): void {
    config(['whatsapp.app_secrets' => ['current-secret']]);
});

it('accepts the signature of the raw body made with the app secret', function (): void {
    expect((new MetaSignature)->valid('{"a":1}', sign('{"a":1}', 'current-secret')))->toBeTrue();
});

it('rejects an altered body, a wrong secret, a missing or malformed header', function (?string $header, string $body): void {
    expect((new MetaSignature)->valid($body, $header))->toBeFalse();
})->with([
    'altered body' => [sign('{"a":1}', 'current-secret'), '{"a":2}'],
    'wrong secret' => [sign('{"a":1}', 'other'), '{"a":1}'],
    'no header' => [null, '{"a":1}'],
    'empty header' => ['', '{"a":1}'],
    'no prefix' => [hash_hmac('sha256', '{"a":1}', 'current-secret'), '{"a":1}'],
    'another algorithm prefix' => ['sha1='.hash_hmac('sha256', '{"a":1}', 'current-secret'), '{"a":1}'],
    'uppercase prefix' => ['SHA256='.hash_hmac('sha256', '{"a":1}', 'current-secret'), '{"a":1}'],
    'truncated' => [substr(sign('{"a":1}', 'current-secret'), 0, -2), '{"a":1}'],
]);

it('accepts the previous secret during a rotation, and only the ones in the list', function (): void {
    config(['whatsapp.app_secrets' => ['new-secret', 'old-secret']]);

    expect((new MetaSignature)->valid('x', sign('x', 'old-secret')))->toBeTrue()
        ->and((new MetaSignature)->valid('x', sign('x', 'new-secret')))->toBeTrue()
        ->and((new MetaSignature)->valid('x', sign('x', 'older')))->toBeFalse();
});

it('rejects everything when no secret is configured', function (): void {
    config(['whatsapp.app_secrets' => []]);

    expect((new MetaSignature)->valid('x', sign('x', '')))->toBeFalse();
});
