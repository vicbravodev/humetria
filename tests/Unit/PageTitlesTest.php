<?php

declare(strict_types=1);

/*
 * The title of a tab is what a person sees first (and the only thing when the tab is narrow), so the pages follow one
 * shape: the page first, then its context ("Horarios · Asistencia"), and the app adds " - Humetría". These rules read
 * the <Head title> of every page, so a new page cannot bring a title that is empty, long, or the same as another page's.
 */

/**
 * The title of each page of the app, with the file it is in: a text keeps its words, a template its `${…}` as `{}`.
 *
 * @return list<array{title: string, file: string}>
 */
function pageTitles(): array
{
    $titles = [];
    $root = dirname(__DIR__, 2).'/resources/js/pages';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'tsx') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $name = substr($file->getPathname(), strlen($root) + 1);

        preg_match_all('/<Head title=(?:"([^"]*)"|\{`([^`]*)`\})/', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $titles[] = ['title' => (string) preg_replace('/\$\{[^}]*\}/', '{}', $match[2] ?? $match[1]), 'file' => $name];
        }
    }

    return $titles;
}

it('gives every page a title, or the head of a public page (SeoHead), which takes its title from lang/es/seo.php', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js/pages';
    $without = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $name = substr($file->getPathname(), strlen($root) + 1);

        if ($file->getExtension() === 'tsx' && ! str_contains((string) file_get_contents($file->getPathname()), '<Head') && ! str_contains((string) file_get_contents($file->getPathname()), '<SeoHead')) {
            $without[] = $name;
        }
    }

    expect($without)->toBe([]);
});

it('keeps the written title of a page short enough for a tab', function (): void {
    $long = array_filter(pageTitles(), fn (array $page): bool => ! str_contains($page['title'], '{}') && mb_strlen($page['title']) > 40);

    expect(array_column($long, 'file'))->toBe([]);
});

it('never leaves a title ending in a separator, as a missing name would', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js/pages';
    $open = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'tsx' && preg_match("/<Head title=\\{`[^`]*(\\?\\? ''|\\|\\| '')/", (string) file_get_contents($file->getPathname())) === 1) {
            $open[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($open)->toBe([]);
});

it('does not give two pages the same title, so two tabs can be told apart', function (): void {
    // The same page for two audiences (the one a company shares by link, the one inside), and the two doors to the portal.
    $allowed = ['Enlace no disponible', 'Entrar a mi portal', 'Informe · {}', 'Reporte · {}'];
    $files = [];

    foreach (pageTitles() as $page) {
        if (! in_array($page['title'], $allowed, true) && ! str_starts_with($page['title'], '{}')) {
            $files[$page['title']][$page['file']] = true;
        }
    }

    $repeated = array_filter($files, fn (array $in): bool => count($in) > 1);

    expect(array_map(fn (array $in): array => array_keys($in), $repeated))->toBe([]);
});

it('has no generic title that does not say what it is about', function (): void {
    $generic = ['Resumen', 'Marcar', 'Accesos', 'Cuentas', 'Primera vez', 'Tendencia', 'Escenarios', 'Prestaciones', 'Tabulador', 'Perfil', 'Seguridad', 'Apariencia'];
    $found = array_filter(pageTitles(), fn (array $page): bool => in_array($page['title'], $generic, true));

    expect(array_column($found, 'title'))->toBe([]);
});
