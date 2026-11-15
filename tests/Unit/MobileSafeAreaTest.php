<?php

declare(strict_types=1);

/*
 * The phone's bottom bar floats above the home indicator (mobile-tab-bar.tsx) and the buttons and the page's end must
 * clear it by the same amount. That needs the viewport to extend under the notch (`viewport-fit=cover`, or the safe-area
 * insets are 0 in a PWA) and one shared offset instead of a fixed `bottom-24` that ignores the inset.
 */

function mobileSafeAreaSource(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

it('lets the viewport extend under the notch so the safe-area insets are real', function (): void {
    expect(mobileSafeAreaSource('resources/views/app.blade.php'))->toContain('viewport-fit=cover');
});

it('clears the tab bar by one shared offset that includes the safe-area inset', function (): void {
    expect(mobileSafeAreaSource('resources/css/app.css'))->toContain('--tab-bar-clearance:')
        ->and(mobileSafeAreaSource('resources/js/components/assistant/humi-launcher.tsx'))->toContain('bottom-(--tab-bar-clearance)')
        ->and(mobileSafeAreaSource('resources/js/components/first-steps/first-steps-launcher.tsx'))->toContain('bottom-(--tab-bar-clearance)')
        ->and(mobileSafeAreaSource('resources/js/layouts/app/app-sidebar-layout.tsx'))->toContain('pb-(--tab-bar-clearance)');
});
