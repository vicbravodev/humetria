<?php

declare(strict_types=1);

/** @return list<string> */
function tourAnchorsIn(string $glob, string $pattern): array
{
    $found = [];
    foreach (glob(dirname(__DIR__, 2).'/'.$glob, GLOB_BRACE) ?: [] as $file) {
        preg_match_all($pattern, (string) file_get_contents($file), $matches);
        array_push($found, ...$matches[1]);
    }

    return array_values(array_unique($found));
}

it('has in the pages every control a tour points at', function (): void {
    $targets = tourAnchorsIn('resources/js/lib/first-steps/steps.ts', "/target: '([a-z-]+)'/");
    $anchors = tourAnchorsIn('resources/js/{pages,components}/{*,*/*}.tsx', '/data-tour="([a-z-]+)"/');

    expect($targets)->not->toBeEmpty()
        ->and(array_diff($targets, $anchors))->toBe([])
        ->and(array_diff($anchors, $targets))->toBe([]);
});
