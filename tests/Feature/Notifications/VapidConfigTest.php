<?php

declare(strict_types=1);

use App\Modules\Notifications\Support\VapidConfig;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;

mutates(VapidConfig::class);

/** @param  array<string, mixed>  $override */
function vapidSettings(array $override = []): void
{
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com', ...$override]]);
}

it('reads the keys and the subject of the company that sends', function (): void {
    vapidSettings();

    $vapid = VapidConfig::fromConfig();

    expect($vapid?->publicKey())->toBe(config('services.vapid.public_key'))
        ->and($vapid?->auth())->toBe(['VAPID' => ['subject' => 'mailto:soporte@humetria.com', 'publicKey' => config('services.vapid.public_key'), 'privateKey' => config('services.vapid.private_key')]]);
});

it('turns the push off, saying why, when a key is missing', function (string $key): void {
    vapidSettings([$key => null]);
    Log::spy();

    expect(VapidConfig::fromConfig())->toBeNull();

    Log::shouldHaveReceived('warning')->with('push.unconfigured', ['reason' => 'missing_keys'])->once();
})->with(['public_key', 'private_key', 'subject']);

it('treats an empty key as missing', function (): void {
    vapidSettings(['private_key' => '']);

    expect(VapidConfig::fromConfig())->toBeNull();
});

it('accepts a subject that is mailto: or https:// and refuses any other', function (string $subject, bool $valid): void {
    vapidSettings(['subject' => $subject]);
    Log::spy();

    expect(VapidConfig::fromConfig() !== null)->toBe($valid);

    if (! $valid) {
        Log::shouldHaveReceived('warning')->with('push.unconfigured', ['reason' => 'subject_invalid'])->once();
    }
})->with([
    'mailto' => ['mailto:a@b', true],
    'https' => ['https://humetria.com', true],
    'a mailto written in capitals' => ['MAILTO:a@b', false],
    'https in capitals' => ['HTTPS://humetria.com', false],
    'http' => ['http://x', false],
    'an address without mailto' => ['x@y', false],
    'a space before' => [' mailto:a@b', false],
]);

it('says nothing of the keys in the log', function (): void {
    vapidSettings(['subject' => 'x@y']);
    Log::spy();

    VapidConfig::fromConfig();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), (string) config('services.vapid.private_key')));
});
