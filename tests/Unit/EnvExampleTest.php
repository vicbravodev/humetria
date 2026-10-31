<?php

declare(strict_types=1);

/*
 * .env.example is the list of settings a new environment fills: every variable config/ reads appears there,
 * unless the package that owns the setting reads it in its own config (those are the package's documented defaults).
 */

// Read by config/ and deliberately left out of .env.example, each with its reason.
const ENV_EXAMPLE_OMISSIONS = [
    'DB_SSLMODE' => "Laravel's default 'prefer' works in Sail and in production behind the provider's settings.",
    'POSTMARK_API_KEY' => 'A mail provider of the Laravel skeleton the app does not use: mail goes through SMTP.',
    'RESEND_API_KEY' => 'A mail provider of the Laravel skeleton the app does not use: mail goes through SMTP.',
];

/** @return list<string> */
function envKeysReadIn(string $pattern): array
{
    $keys = [];

    foreach (glob($pattern) ?: [] as $file) {
        preg_match_all("/\benv\('([A-Z0-9_]+)'/", (string) file_get_contents($file), $matches);
        $keys = [...$keys, ...$matches[1]];
    }

    return array_values(array_unique($keys));
}

it('lists in .env.example every variable the app config reads', function (): void {
    $root = dirname(__DIR__, 2);
    preg_match_all('/^#?\s*([A-Z0-9_]+)=/m', (string) file_get_contents($root.'/.env.example'), $listed);
    $packages = [...envKeysReadIn($root.'/vendor/laravel/framework/config/*.php'), ...envKeysReadIn($root.'/vendor/*/*/config/*.php')];

    $missing = array_values(array_diff(envKeysReadIn($root.'/config/*.php'), $listed[1], $packages, array_keys(ENV_EXAMPLE_OMISSIONS)));
    sort($missing);

    expect($missing)->toBe([], 'Missing from .env.example: '.implode(', ', $missing));
});

it('omits only variables the config still reads', function (): void {
    expect(array_diff(array_keys(ENV_EXAMPLE_OMISSIONS), envKeysReadIn(dirname(__DIR__, 2).'/config/*.php')))->toBe([]);
});
