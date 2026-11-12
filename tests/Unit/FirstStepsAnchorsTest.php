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

it('runs exactly one tour runner per page: the dock owns it, the bare component only where there is no dock', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js';
    $dock = (string) file_get_contents($root.'/components/first-steps/first-steps-dock.tsx');
    $withTour = array_map(
        fn (string $file): string => str_replace($root.'/', '', $file),
        array_values(array_filter(
            glob($root.'/{pages,layouts,components}/{*,*/*,*/*/*}.tsx', GLOB_BRACE) ?: [],
            fn (string $file): bool => str_contains((string) file_get_contents($file), '<FirstStepsTour'),
        )),
    );

    // The dock calls the hook itself, so it must not also render the component (a second runner), but it keeps the styles.
    expect($dock)->toContain('useFirstStepsTour()')
        ->and($dock)->toContain('first-steps-tour.css')
        ->and($withTour)->toBe(['pages/tenancy/onboarding.tsx']);
});
