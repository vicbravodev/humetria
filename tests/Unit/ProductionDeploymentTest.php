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

describe('compose.production.yaml', function (): void {
    it('keeps web, horizon and scheduler on the same image tag, the one HUMETRIA_TAG says', function (): void {
        foreach (['web', 'horizon', 'scheduler'] as $service) {
            expect(productionService($service)['image'] ?? null)
                ->toBe('${HUMETRIA_IMAGE:-ghcr.io/vicbravodev/humetria}:${HUMETRIA_TAG:-latest}');
        }
    });

    it('runs the ssr service from its own image, paired by the same tag', function (): void {
        expect(productionService('ssr')['image'] ?? null)
            ->toBe('${HUMETRIA_SSR_IMAGE:-ghcr.io/vicbravodev/humetria-ssr}:${HUMETRIA_TAG:-latest}');
    });

    it('gives ssr no secrets, no ports, no routes, no dependencies and no volumes', function (): void {
        $ssr = productionService('ssr');

        foreach (['env_file', 'ports', 'expose', 'labels', 'depends_on', 'volumes', 'extra_hosts'] as $key) {
            expect($ssr)->not->toHaveKey($key);
        }

        expect($ssr['environment'] ?? [])->toBe(['NODE_ENV' => 'production', 'NODE_OPTIONS' => '--max-old-space-size=256']);
    });

    it('locks the ssr container down and bounds what it can use', function (): void {
        expect(productionService('ssr'))->toMatchArray([
            'restart' => 'unless-stopped',
            'init' => true,
            'read_only' => true,
            'tmpfs' => ['/tmp'],
            'cap_drop' => ['ALL'],
            'security_opt' => ['no-new-privileges:true'],
            'mem_limit' => '384m',
            'cpus' => '1.0',
            'pids_limit' => 100,
        ]);
    });

    it('lets Docker ask the healthcheck for a real render, every 30 seconds', function (): void {
        expect(productionService('ssr')['healthcheck'] ?? null)->toBe([
            'test' => ['CMD', 'node', 'scripts/ssr/healthcheck.mjs'],
            'interval' => '30s',
            'timeout' => '5s',
            'retries' => 3,
            'start_period' => '20s',
        ]);
    });

    it('puts ssr only in an internal network that web shares, never in the ones Traefik or the database use', function (): void {
        $networks = deploymentYaml('compose.production.yaml')['networks'] ?? [];

        expect($networks['ssr-internal'] ?? null)->toBe(['internal' => true])
            ->and(productionService('ssr')['networks'] ?? null)->toBe(['ssr-internal' => ['aliases' => ['humetria-ssr']]])
            ->and(productionService('web')['networks'] ?? null)->toBe(['default', 'dokploy-network', 'ssr-internal'])
            ->and(productionService('horizon')['networks'] ?? null)->toBe(['default', 'dokploy-network'])
            ->and(productionService('scheduler')['networks'] ?? null)->toBe(['default', 'dokploy-network']);
    });

    it('does not make web wait for ssr: a broken Node must not stop the site from starting', function (): void {
        expect(array_keys((array) (productionService('web')['depends_on'] ?? [])))->toBe(['valkey']);
    });
});

describe('.env.production.example', function (): void {
    it('turns SSR on, pointed at the ssr service and without a bundle in web', function (): void {
        $production = deploymentFile('.env.production.example');

        expect($production)->toMatch('/^INERTIA_SSR_ENABLED=true$/m')
            ->toMatch('/^INERTIA_SSR_URL=http:\/\/humetria-ssr:13714$/m')
            ->toMatch('/^INERTIA_SSR_ENSURE_BUNDLE=false$/m');
    });

    it('does not set APP_REVISION: an empty one in .env would overwrite the commit the image carries', function (): void {
        expect(deploymentFile('.env.production.example'))->not->toMatch('/^APP_REVISION=/m');
    });

    it('describes HUMETRIA_IMAGE as a repository and the tag as HUMETRIA_TAG', function (): void {
        $production = deploymentFile('.env.production.example');

        expect($production)->toMatch('/^# HUMETRIA_IMAGE=ghcr\.io\/vicbravodev\/humetria$/m')
            ->toMatch('/^# HUMETRIA_TAG=sha-<commit>$/m');
    });
});

describe('workflows', function (): void {
    it('publishes both images from one commit, each with its own cache, before the webhook', function (): void {
        $builds = array_values(array_filter(deploySteps(), fn (array $step): bool => str_starts_with((string) ($step['uses'] ?? ''), 'docker/build-push-action')));
        $sha = '${{ github.event.workflow_run.head_sha || github.sha }}';

        expect($builds)->toHaveCount(2);

        foreach ([['app', ''], ['ssr', '-ssr']] as $position => [$target, $suffix]) {
            $with = $builds[$position]['with'];

            expect($with['target'])->toBe($target)
                ->and($with['push'])->toBeTrue()
                ->and($with['build-args'])->toBe("GIT_SHA={$sha}")
                ->and($with['tags'])->toContain("ghcr.io/\${{ github.repository }}{$suffix}:latest")
                ->and($with['tags'])->toContain("ghcr.io/\${{ github.repository }}{$suffix}:sha-{$sha}")
                ->and($with['cache-from'])->toBe("type=gha,scope={$target}")
                ->and($with['cache-to'])->toBe("type=gha,mode=max,scope={$target}");
        }

        $names = array_map(fn (array $step): string => (string) ($step['name'] ?? $step['uses'] ?? ''), deploySteps());

        expect(array_search('Redeploy en Dokploy', $names, true))->toBeGreaterThan(array_search($builds[1]['uses'], $names, true));
    });

    it('checks the site from outside after the webhook, against the commit just published', function (): void {
        $steps = deploySteps();
        $check = end($steps);

        expect($check['run'])->toContain('node scripts/synthetic/check-landing.mjs')
            ->toContain('--expect-revision ${{ github.event.workflow_run.head_sha || github.sha }}')
            ->toContain('--wait 600')
            ->and($check['if'])->toBe("env.DOKPLOY_WEBHOOK_URL != ''");
    });

    it('checks the live site every 15 minutes, and on demand', function (): void {
        $workflow = deploymentYaml('.github/workflows/synthetic.yml');
        $steps = (array) ($workflow['jobs']['landing']['steps'] ?? []);
        $runs = implode("\n", array_map(fn (array $step): string => (string) ($step['run'] ?? ''), $steps));

        expect($workflow['on']['schedule'] ?? null)->toBe([['cron' => '*/15 * * * *']])
            ->and($workflow['on'])->toHaveKey('workflow_dispatch')
            ->and($workflow['permissions'] ?? null)->toBe(['contents' => 'read'])
            ->and($runs)->toContain('node scripts/synthetic/check-landing.mjs --url https://humetria.com.mx/');
    });
});
