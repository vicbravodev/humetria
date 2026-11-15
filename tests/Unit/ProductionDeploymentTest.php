<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * What production runs, pinned: the Dockerfile with its two final images (app and ssr), compose.production.yaml with the
 * `ssr` service in a network of its own, and the workflows that publish both images from one commit and check the site from
 * outside. None of it can run here (no Docker, no Dokploy): these tests fail the day someone loosens what the design says
 * (docs/superpowers/specs/2026-11-14-landing-seo-design-v2.md, sections 7 and 12.6). The manual checks are in docs/operations/ssr.md.
 */

function deploymentFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

/** @return array<string, mixed> */
function deploymentYaml(string $path): array
{
    $parsed = Yaml::parseFile(dirname(__DIR__, 2).'/'.$path);

    return is_array($parsed) ? $parsed : [];
}

/**
 * The lines of one stage of the Dockerfile, from its FROM to the next one.
 */
function dockerStage(string $name): string
{
    preg_match('/^FROM [^\n]+ AS '.preg_quote($name, '/').'\n(.*?)(?=^FROM |\z)/ms', deploymentFile('Dockerfile'), $match);

    return $match[1] ?? '';
}

/** @return array<string, mixed> */
function productionService(string $name): array
{
    $services = deploymentYaml('compose.production.yaml')['services'] ?? [];

    return is_array($services) && is_array($services[$name] ?? null) ? $services[$name] : [];
}

/** @return list<array<string, mixed>> */
function deploySteps(): array
{
    $jobs = deploymentYaml('.github/workflows/deploy.yml')['jobs'] ?? [];

    return is_array($jobs) && is_array($jobs['image']['steps'] ?? null) ? $jobs['image']['steps'] : [];
}

describe('Dockerfile', function (): void {
    it('builds the client and the SSR bundle in one run, stamped with the commit', function (): void {
        $assets = dockerStage('assets');

        expect($assets)->toContain('ARG GIT_SHA=unknown')
            ->toContain('ENV VITE_BUILD_REVISION=$GIT_SHA')
            ->toContain('npm run build:ssr')
            ->not->toMatch('/npm run build\b(?!:)/');
    });

    it('ends in the app image, so a plain docker build still makes the web image', function (): void {
        preg_match_all('/^FROM [^\n]+ AS (\w+)/m', deploymentFile('Dockerfile'), $stages);

        expect(end($stages[1]))->toBe('app')
            ->and(array_search('ssr', $stages[1], true))->toBeLessThan(array_search('app', $stages[1], true));
    });

    it('stamps the app image with the same commit', function (): void {
        expect(dockerStage('app'))->toContain('ARG GIT_SHA=unknown')->toContain('ENV APP_REVISION=${GIT_SHA}');
    });

    it('makes an ssr image of Node, the bundle and the scripts, running as node, and nothing else', function (): void {
        $ssr = dockerStage('ssr');

        expect(deploymentFile('Dockerfile'))->toContain("FROM \${NODE_IMAGE} AS ssr\n");
        expect($ssr)->toContain('COPY --from=assets --chown=node:node /var/www/html/bootstrap/ssr ./bootstrap/ssr')
            ->toContain('COPY --chown=node:node scripts/ssr ./scripts/ssr')
            ->toContain('tests/Ssr/fixtures/landing-expected.json')
            ->toContain('{"type":"module"}')
            ->toContain('USER node')
            ->toContain('CMD ["node", "scripts/ssr/supervisor.mjs"]')
            ->toContain('HEALTHCHECK')
            ->not->toContain('COPY . ')
            ->not->toContain('.env')
            ->not->toContain('vendor')
            ->not->toContain('ARG PHP_IMAGE')
            ->not->toContain('FROM source')
            ->not->toContain('FROM base');
    });

    it('keeps a local bundle out of the build context, and the scripts and the expected list in', function (): void {
        $ignored = deploymentFile('.dockerignore');

        expect($ignored)->toMatch('/^bootstrap\/ssr$/m')
            ->and($ignored)->not->toMatch('/^scripts/m')
            ->and($ignored)->toMatch('/^!tests\/Ssr\/fixtures\/landing-expected\.json$/m');
    });
});
