<?php

declare(strict_types=1);

/*
 * Rediseño v2 (C4): a file is dropped on or chosen from `components/humetria/file-drop-zone.tsx`, never through the
 * native «Choose File». Every module adopted it, so it holds for all of resources/js but the shadcn primitives and the
 * zone itself, which keeps the real input inside.
 */
it('chooses files only through the drop zone', function (): void {
    $root = realpath(__DIR__.'/../../resources/js').'/';
    $native = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = $file instanceof SplFileInfo ? substr($file->getPathname(), strlen($root)) : '';

        if (str_ends_with($path, '.tsx') && ! str_starts_with($path, 'components/ui/') && $path !== 'components/humetria/file-drop-zone.tsx'
            && str_contains((string) file_get_contents($root.$path), 'type="file"')) {
            $native[] = $path;
        }
    }

    sort($native);

    expect($native)->toBe([]);
});
