<?php

declare(strict_types=1);

/*
 * Rediseño v2 (C4): a file is dropped on or chosen from `components/humetria/file-drop-zone.tsx`, never through the
 * native «Choose File». Each module joins the list when its PR swaps its fields (PREGUNTAS-FRONT-BACK.md, C4).
 */
it('chooses files only through the drop zone in the modules that adopted it', function (string $module): void {
    $native = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../../resources/js/components/{$module}", FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && str_contains((string) file_get_contents($file->getPathname()), 'type="file"')) {
            $native[] = $file->getFilename();
        }
    }

    expect($native)->toBe([]);
})->with(['telework']);
