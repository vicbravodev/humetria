<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** What `notifications:vapid-keys` prints, as `[public key, private key]`. */
function printedKeys(): array
{
    $exit = Artisan::call('notifications:vapid-keys');
    $output = Artisan::output();
    preg_match('/^VAPID_PUBLIC_KEY=(\S+)$/m', $output, $public);
    preg_match('/^VAPID_PRIVATE_KEY=(\S+)$/m', $output, $private);

    expect($exit)->toBe(0);

    return [$public[1] ?? '', $private[1] ?? '', $output];
}

it('prints a pair of keys for the .env: 65 and 32 bytes in base64url', function (): void {
    [$public, $private] = printedKeys();

    expect($public)->toMatch('/^[A-Za-z0-9_-]{87}$/')->and($private)->toMatch('/^[A-Za-z0-9_-]{43}$/');
});

it('prints different keys every time', function (): void {
    expect(printedKeys()[0])->not->toBe(printedKeys()[0]);
});

it('warns that changing the keys makes every phone subscribe again', function (): void {
    expect(printedKeys()[2])->toContain('invalida');
});

it('does not write the .env, nor reach any address', function (): void {
    $before = File::exists(base_path('.env')) ? File::get(base_path('.env')) : null;
    Http::fake();

    printedKeys();

    expect(File::exists(base_path('.env')) ? File::get(base_path('.env')) : null)->toBe($before);
    Http::assertNothingSent();
});

it('logs that keys were made, never the keys', function (): void {
    Log::spy();

    [$public, $private] = printedKeys();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'push.vapid_keys_generated' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $public) && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), $private))->once();
});
