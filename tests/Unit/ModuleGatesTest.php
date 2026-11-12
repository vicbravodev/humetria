<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\Module;

/**
 * The files outside Tenancy that may read `->blocked` of a company directly, each with its reason: a tool that is no module.
 * Everything else asks `->stopped('module')`, which is blocked or without that module.
 */
const MAY_READ_BLOCKED = [
    'app/Modules/Compensation/Http/Controllers/SharedReportController.php' => 'the CCT calculator is the union\'s and every company\'s: it is no module',
    'app/Modules/FirstSteps/Support/CompanyFirstSteps.php' => 'the first steps guide every owner through the platform: they are no module, only a blocked company loses them',
];

/** @return list<string> */
function moduleSourceFiles(): array
{
    $root = dirname(__DIR__, 2).'/';
    $files = [];

    foreach (glob($root.'app/Modules/*', GLOB_ONLYDIR) ?: [] as $module) {
        if (basename($module) === 'Tenancy') {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($module, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($root));
            }
        }
    }

    sort($files);

    return $files;
}

it('makes every portal, device and job ask whether the company has the module, not only whether it is blocked', function (): void {
    $root = dirname(__DIR__, 2).'/';
    $reading = array_values(array_filter(
        moduleSourceFiles(),
        fn (string $file): bool => ! array_key_exists($file, MAY_READ_BLOCKED) && preg_match('/->blocked\b/', (string) file_get_contents($root.$file)) === 1,
    ));

    expect($reading)->toBe([]);
});

it('only asks about modules that exist', function (): void {
    $root = dirname(__DIR__, 2).'/';
    $known = array_map(fn (Module $module): string => $module->value, Module::cases());
    $asked = [];

    foreach (moduleSourceFiles() as $file) {
        preg_match_all("/->(?:stopped|allows)\('([^']*)'\)/", (string) file_get_contents($root.$file), $matches);
        $asked = [...$asked, ...$matches[1]];
    }

    expect($asked)->not->toBeEmpty()->and(array_values(array_diff(array_unique($asked), $known)))->toBe([]);
});
