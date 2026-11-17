<?php

declare(strict_types=1);

/*
 * One width rule for every page (docs/design-system/design-system.md; tests/Unit/PageWidthTest.php keeps the classes):
 * the frame of the app sets the width, so moving between modules the content starts at the same column, and no page
 * scrolls sideways. Employees never had a cap of its own: it is the column the others must start at.
 */

it('starts the content of every module at the same column, with no sideways scroll', function (int $width): void {
    $url = signInToPanel();
    $edge = <<<'JS'
        () => {
            const title = document.querySelector('[data-slot="page-body"] h1');
            return title === null ? null : Math.round(title.getBoundingClientRect().left);
        }
        JS;
    $scrolls = '() => document.documentElement.scrollWidth > document.documentElement.clientWidth';
    $edges = [];

    foreach (['employees', 'attendance', 'vacations', 'complaints', 'compensation', 'disclosure', 'telework', 'seating', 'telework/settings'] as $path) {
        $page = visit("{$url}/{$path}")->resize($width, 900)->assertNoJavaScriptErrors();
        $edges[$path] = $page->script($edge);
        expect($page->script($scrolls))->toBeFalse("{$path} scrolls sideways at {$width} px");
    }

    expect(array_unique($edges))->toHaveCount(1, json_encode($edges, JSON_THROW_ON_ERROR));
})->with([1440, 1920]);
