<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/ci-code-changed.sh tells the workflows whether a change needs the pipeline. It answers "false" only when every
 * changed path is documentation nothing reads: tests.yml then skips its jobs and deploy.yml does not redeploy.
 */

/**
 * What the script answers for these changed paths.
 *
 * @param  list<string>  $paths
 */
function pipelineNeededFor(array $paths): string
{
    $process = new Process(['bash', dirname(__DIR__, 2).'/scripts/ci-code-changed.sh']);
    $process->setInput(implode("\n", $paths));
    $process->mustRun();

    return trim($process->getOutput());
}

/**
 * The documents a string of the PHP code names (a path a test, the app or its configuration reads), sorted: the ones
 * under docs/ and the markdown files at the root, such as CLAUDE.md. Comments are left out: a document a comment mentions
 * is read by nobody. This file is left out too: its own examples are not reads.
 *
 * @return list<string>
 */
function documentsTheCodeReads(): array
{
    $root = dirname(__DIR__, 2);
    $documents = [];

    foreach (['tests', 'app', 'config', 'database', 'routes', 'scripts'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getPathname() === __FILE__) {
                continue;
            }

            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                    continue;
                }

                if (preg_match_all('~docs/[\w./-]+\.\w+~', $token[1], $matches) > 0) {
                    $documents = [...$documents, ...$matches[0]];
                }

                // A string that is nothing but the name of a markdown file: one at the root, read by its name.
                if (preg_match('~^[\'"]([\w.-]+\.md)[\'"]$~', $token[1], $atRoot) === 1) {
                    $documents[] = $atRoot[1];
                }
            }
        }
    }

    $documents = array_values(array_unique($documents));
    sort($documents);

    return $documents;
}

it('skips the pipeline when only documentation changed', function (array $paths): void {
    expect(pipelineNeededFor($paths))->toBe('false');
})->with([
    'a design' => [['docs/superpowers/specs/2026-11-14-worker-access-design.md']],
    'a prototype and a note at the root' => [['docs/rediseno/MAPA.md', 'concepts_brief.md']],
    'the guides for the agents and the readme' => [['AGENTS.md', 'README.md']],
    'a capture of the design system' => [['docs/design-system/capturas/tablero-claro.png']],
]);

it('runs the pipeline when anything else changed', function (array $paths): void {
    expect(pipelineNeededFor($paths))->toBe('true');
})->with([
    'code' => [['app/Models/User.php']],
    'code next to documentation' => [['docs/testing.md', 'routes/web.php']],
    'a workflow' => [['.github/workflows/tests.yml']],
    'a readme inside the code' => [['app/Modules/Assistant/README.md']],
    'a file at the root that is not markdown' => [['composer.json']],
    'a norm the instrument tests read' => [['docs/normas/nom-035/texto-de-referencia.md']],
    'the criteria the assistant reads' => [['docs/normas/vacaciones/criterios.md']],
    'the item bank a test reads' => [['docs/superpowers/specs/2026-11-09-engagement-item-bank.md']],
    'the operations manual a test reads' => [['docs/operations/ssr.md']],
    'the share image a test reads' => [['docs/design-system/og/og-v1.html']],
    'the rules of the project, which a test reads' => [['CLAUDE.md']],
    'no path at all, when the change could not be read' => [[]],
]);

it('runs the pipeline for every document the code reads', function (): void {
    $documents = documentsTheCodeReads();

    // The scan finds them: an empty list would pass in silence.
    expect($documents)->toContain('docs/normas/nom-035/texto-de-referencia.md');

    $skipped = array_values(array_filter($documents, fn (string $document): bool => pipelineNeededFor([$document]) !== 'true'));

    // A document a test or the app reads is code: add it to the list of scripts/ci-code-changed.sh, next to docs/normas.
    expect($skipped)->toBe([]);
});
