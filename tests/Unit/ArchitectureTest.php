<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A module lives in app/Modules/{Module}. Only these sub-namespaces are its public API;
 * everything else is internal and may only be used from inside the module.
 */
const PUBLIC_MODULE_NAMESPACES = ['Contracts', 'Events', 'Data'];

/** @return list<string> */
function modules(): array
{
    return array_map(basename(...), glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: []);
}

/** @return list<string> */
function internalNamespacesOf(string $module): array
{
    $directories = array_map(basename(...), glob(__DIR__."/../../app/Modules/{$module}/*", GLOB_ONLYDIR) ?: []);

    return array_values(array_map(
        fn (string $directory): string => "App\\Modules\\{$module}\\{$directory}",
        array_diff($directories, PUBLIC_MODULE_NAMESPACES),
    ));
}

arch()->preset()->php();

arch()->preset()->security();

arch('every file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('comparisons are strict')
    ->expect('App')
    ->toUseStrictEquality();

arch('concrete classes are final')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring('App\Http\Controllers\Controller');

arch('Eloquent models live only in Models namespaces')
    ->expect('App')
    ->classes()
    ->not->toExtend(Model::class)
    ->ignoring(['App\Models', ...array_map(fn (string $module): string => "App\\Modules\\{$module}\\Models", modules())]);

arch('form requests live only in Http\Requests namespaces')
    ->expect('App')
    ->classes()
    ->not->toExtend(FormRequest::class)
    ->ignoring(['App\Http\Requests', ...array_map(fn (string $module): string => "App\\Modules\\{$module}\\Http\\Requests", modules())]);

/*
 * Each rule targets one existing namespace: Pest silently passes a multi-target
 * expectation when any target namespace does not exist.
 */
$moduleInternals = array_merge([], ...array_map(internalNamespacesOf(...), modules()));

foreach (['App\Models', 'App\Http', 'App\Actions', 'App\Concerns', 'App\Providers'] as $sharedNamespace) {
    if ($moduleInternals !== []) {
        arch("{$sharedNamespace} does not depend on module internals")
            ->expect($sharedNamespace)
            ->not->toUse($moduleInternals);
    }
}

foreach (modules() as $module) {
    arch("{$module} contracts are interfaces")
        ->expect("App\\Modules\\{$module}\\Contracts")
        ->toBeInterfaces();

    arch("{$module} data objects are readonly")
        ->expect("App\\Modules\\{$module}\\Data")
        ->classes()
        ->toBeReadonly();

    arch("{$module} actions expose handle()")
        ->expect("App\\Modules\\{$module}\\Actions")
        ->classes()
        ->toHaveMethod('handle');

    foreach (internalNamespacesOf($module) as $internalNamespace) {
        // The module's factories (database/factories/{Module}, CLAUDE.md) build its models, so they count as inside it.
        arch("{$internalNamespace} is internal to the {$module} module")
            ->expect($internalNamespace)
            ->toOnlyBeUsedIn(["App\\Modules\\{$module}", "Database\\Factories\\{$module}"]);

        if (str_ends_with($internalNamespace, '\\Actions') || str_ends_with($internalNamespace, '\\Models')) {
            arch("{$internalNamespace} does not depend on HTTP")
                ->expect($internalNamespace)
                ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
        }
    }
}
