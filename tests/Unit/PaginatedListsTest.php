<?php

declare(strict_types=1);

/*
 * A page never takes a list of records as an array (CLAUDE.md, «Listas»): an array prop is only a fixed catalog or a
 * short summary, each named here with why. A page that takes a Paginated prop is checked at runtime by
 * tests/Feature/PaginatedListsTest.php.
 */

/** "page:prop" => why it may be an array. */
const ARRAY_PROPS = [
    'organization/employees:genders' => 'catálogo fijo: el enum Gender',
    'tenancy/work-centers:states' => 'catálogo fijo: las 32 entidades',
    'tenancy/members:roles' => 'catálogo fijo: los roles que se pueden dar',
    'dashboard:nextSteps' => 'resumen de pendientes: cinco a lo más, con el total aparte',
    'dashboard:levels' => 'catálogo fijo: los cinco niveles de riesgo',
    'campaigns/survey:instruments' => 'las guías que contesta una persona: tres a lo más',
    'campaigns/show:instruments' => 'resumen por guía de la evaluación: tres a lo más',
    'campaigns/show:standings' => 'conteos por situación: cinco',
    'campaigns/show:replacementPool' => 'conteos por género: tres a lo más',
    'results/show:items' => 'catálogo fijo: las preguntas de la guía, contadas (72 a lo más)',
    'results/report:durations' => 'catálogo fijo: las seis duraciones de un enlace',
    'results/report:levels' => 'catálogo fijo: los cinco niveles de riesgo',
    'results/shared-report:levels' => 'catálogo fijo: los cinco niveles de riesgo',
    'results/show:levels' => 'catálogo fijo: los cinco niveles de riesgo',
];

/** @return array<string, string> page => its source */
function pageSources(): array
{
    $root = __DIR__.'/../../resources/js/pages/';
    $sources = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getPathname(), '.tsx')) {
            $sources[substr($file->getPathname(), strlen($root), -strlen('.tsx'))] = (string) file_get_contents($file->getPathname());
        }
    }

    ksort($sources);

    return $sources;
}

it('gives no page a list of records as an array', function (): void {
    $found = [];

    foreach (pageSources() as $page => $source) {
        preg_match_all('/^\s+(\w+)\??: [^;=]*\[\](?: \| null)?;$/m', $source, $matches);

        foreach ($matches[1] as $prop) {
            $found[] = "{$page}:{$prop}";
        }
    }

    sort($found);
    $allowed = array_keys(ARRAY_PROPS);
    sort($allowed);

    expect($found)->toBe($allowed, 'An array prop that is not a fixed catalog or a short summary must be a Paginated<…> instead.');
});

it('checks at runtime every page that takes a paginated list', function (): void {
    $runtime = (string) file_get_contents(__DIR__.'/../Feature/PaginatedListsTest.php');
    $paginated = array_keys(array_filter(pageSources(), fn (string $source): bool => str_contains($source, 'Paginated<')));
    $unchecked = array_values(array_filter($paginated, fn (string $page): bool => ! str_contains($runtime, "'{$page}'")));

    expect($paginated)->not->toBeEmpty()
        ->and($unchecked)->toBe([], 'Pages with a Paginated prop missing from tests/Feature/PaginatedListsTest.php: '.implode(', ', $unchecked));
});
