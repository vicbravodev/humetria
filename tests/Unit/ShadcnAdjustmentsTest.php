<?php

declare(strict_types=1);

function uiComponent(string $name): string
{
    return (string) file_get_contents(__DIR__."/../../resources/js/components/ui/{$name}.tsx");
}

it('leaves no shadcn default that breaks the design system', function (string $forbidden): void {
    $offenders = [];

    foreach ((array) glob(__DIR__.'/../../resources/js/components/ui/*.tsx') as $file) {
        if (str_contains((string) file_get_contents($file), $forbidden)) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([]);
})->with([
    'ring-[3px]', 'ring-ring/50', 'shadow-xs', 'md:text-sm', 'dark:bg-input/30',
    'destructive-foreground', 'dark:bg-destructive/60',
]);

it('keeps shadows only on floating surfaces', function (string $component): void {
    expect(uiComponent($component))->not->toContain('shadow-sm');
})->with(['button', 'input', 'card', 'select', 'checkbox', 'badge', 'toggle']);

it('sizes controls to 44 px and focuses with a solid outline', function (string $component): void {
    expect(uiComponent($component))
        ->toContain('h-11')
        ->toContain('focus-visible:outline-ring');
})->with(['button', 'input', 'select']);

it('never lets outline-none cancel the focus-visible outline', function (): void {
    $offenders = [];

    foreach ((array) glob(__DIR__.'/../../resources/js/components/ui/*.tsx') as $file) {
        $source = (string) file_get_contents($file);

        if (str_contains($source, 'outline-none')
            && str_contains($source, 'focus-visible:outline-2')
            && ! str_contains($source, 'focus-visible:outline-solid')) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([]);
});
