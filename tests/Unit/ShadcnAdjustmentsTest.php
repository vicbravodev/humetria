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
    'destructive-foreground', 'dark:bg-destructive/60', 'ring-ring/', 'outline-ring/',
]);

it('keeps shadows only on floating surfaces', function (string $component): void {
    expect(uiComponent($component))->not->toContain('shadow-sm');
})->with(['button', 'input', 'card', 'select', 'checkbox', 'badge', 'toggle', 'sidebar']);

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

it('sizes buttons as Button.md requires', function (string $size, string $classes): void {
    $sizes = explode('size: {', uiComponent('button'), 2)[1] ?? '';
    preg_match('/\b'.$size.':\s*"([^"]*)"/', $sizes, $match);

    expect(explode(' ', $match[1] ?? ''))->toContain(...explode(' ', $classes));
})->with([
    'default 44 px' => ['default', 'h-11'],
    'sm 36 px visible with a 44 px touch area' => ['sm', "h-9 relative before:absolute before:inset-x-0 before:-inset-y-1 before:content-['']"],
    'lg 52 px' => ['lg', 'h-13'],
    'icon 44x44' => ['icon', 'size-11'],
]);

it('styles the dialog as Dialog.md requires', function (): void {
    expect(uiComponent('dialog'))
        ->toContain('bg-black/48')
        ->toContain('bg-popover')
        ->toContain('rounded-xl')
        ->not->toContain('bg-black/80')
        ->not->toContain('focus:ring-2');
});

it('puts the safe action of a confirmation dialog in an outline button', function (string $component): void {
    $source = (string) file_get_contents(__DIR__."/../../resources/js/components/{$component}.tsx");

    expect($source)->toMatch('/<DialogClose asChild>\s*<Button\s+variant="outline"/');
})->with(['delete-user', 'passkeys/passkey-item']);

it('sizes the checkbox box at 20 px', function (): void {
    expect(uiComponent('checkbox'))->toContain('size-5')->not->toContain('size-4 shrink-0');
});

it('lists select options in body text with a 40 px row', function (): void {
    expect(uiComponent('select'))->toContain('min-h-10')->toContain('text-base')->not->toContain('py-1.5 pr-8 pl-2 text-sm');
});

it('animates what opens and closes with the strong ease-out, from its trigger', function (string $component, string $origin): void {
    expect(uiComponent($component))
        ->toContain('ease-out')
        ->toContain("origin-(--radix-{$origin}-content-transform-origin)");
})->with([
    ['select', 'select'],
    ['popover', 'popover'],
    ['dropdown-menu', 'dropdown-menu'],
]);

it('marks a badge with a dot on a soft fill, never a solid block of color', function (): void {
    expect(uiComponent('badge'))
        ->toContain('before:rounded-full')
        ->toContain('text-[13px]')
        ->not->toContain('bg-primary text-primary-foreground')
        ->not->toContain('bg-destructive text-on-destructive');
});

it('opens a dialog with a short scale-in and closes it faster', function (): void {
    expect(uiComponent('dialog'))
        ->toContain('data-[state=open]:zoom-in-96')
        ->toContain('data-[state=open]:duration-200')
        ->toContain('data-[state=closed]:duration-150')
        ->toContain('ease-out');
});
