<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/** The config files parse their environment strings into lists; read them fresh with the variables set. */
function configWith(string $file, array $env): array
{
    foreach ($env as $key => $value) {
        putenv("{$key}={$value}");
    }

    try {
        return require config_path($file);
    } finally {
        foreach (array_keys($env) as $key) {
            putenv($key);
        }
    }
}

it('reads the recipient keys as version => key', function (): void {
    $keys = configWith('messaging.php', ['MESSAGING_RECIPIENT_KEYS' => '1:YWJj,2:ZGVm'])['recipient_keys'];

    expect($keys)->toBe([1 => 'YWJj', 2 => 'ZGVm']);
});

it('has no recipient keys by default', function (): void {
    expect(configWith('messaging.php', [])['recipient_keys'])->toBe([]);
});

it('accepts the current and the previous app secret during a rotation, and never a null', function (): void {
    expect(configWith('whatsapp.php', ['WHATSAPP_APP_SECRET' => 'new', 'WHATSAPP_APP_SECRET_PREVIOUS' => 'old'])['app_secrets'])->toBe(['new', 'old'])
        ->and(configWith('whatsapp.php', ['WHATSAPP_APP_SECRET' => 'new'])['app_secrets'])->toBe(['new'])
        ->and(configWith('whatsapp.php', [])['app_secrets'])->toBe([]);
});

it('accepts the current and the previous Resend webhook secret, and never a null', function (): void {
    expect(configWith('services.php', ['RESEND_WEBHOOK_SECRET' => 'new', 'RESEND_WEBHOOK_SECRET_PREVIOUS' => 'old'])['resend']['webhook_secrets'])->toBe(['new', 'old'])
        ->and(configWith('services.php', [])['resend']['webhook_secrets'])->toBe([])
        ->and(config('services.resend.webhook_tolerance_seconds'))->toBe(300);
});

it('ships with the function off and unpaused', function (): void {
    expect(config('whatsapp.enabled'))->toBeFalse()
        ->and(config('whatsapp.paused'))->toBeFalse()
        ->and(config('whatsapp.language'))->toBe('es_MX');
});
