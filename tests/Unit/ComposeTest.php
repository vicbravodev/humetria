<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * The services of compose.yaml, by name.
 *
 * @return array<string, array<string, mixed>>
 */
function composeServices(): array
{
    $compose = Yaml::parseFile(dirname(__DIR__, 2).'/compose.yaml');

    return is_array($compose) && is_array($compose['services'] ?? null) ? $compose['services'] : [];
}

// Sail's start-container runs a service's command as `gosu $WWWUSER`: without it, gosu reads the command as the user.
it('gives every service that runs a command through Sail start-container the user to run it as', function (): void {
    $throughStartContainer = array_filter(
        composeServices(),
        fn (array $service): bool => ($service['image'] ?? null) === 'sail-8.5/app' && ! isset($service['entrypoint']) && isset($service['command']),
    );

    expect(array_keys($throughStartContainer))->toBe(['horizon', 'scheduler']);

    foreach ($throughStartContainer as $name => $service) {
        expect($service['environment'] ?? [])->toMatchArray(['WWWUSER' => '${WWWUSER}', 'LARAVEL_SAIL' => 1], "Service [{$name}]");
    }
});
