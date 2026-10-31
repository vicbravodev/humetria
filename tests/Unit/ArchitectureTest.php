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

/*
 * Telework (NOM-037) holds the most personal data of the platform, so its boundaries are spelled out on their own: the
 * loop above already covers every module (no other module uses Telework internals and Telework uses no other module's
 * internals, because each internal namespace may only be used inside its own module), and these rules fail loudly if
 * Telework or one of its namespaces goes missing. They stay few: every arch() rule costs memory in the coverage run.
 */
$teleworkInternals = internalNamespacesOf('Telework');

it('has the Telework namespaces the architecture rules target', function () use ($teleworkInternals): void {
    expect($teleworkInternals)->toContain('App\Modules\Telework\Actions', 'App\Modules\Telework\Models', 'App\Modules\Telework\Support', 'App\Modules\Telework\Http');
});

foreach (['Actions', 'Models'] as $layer) {
    arch("Telework {$layer} do not depend on HTTP")
        ->expect("App\\Modules\\Telework\\{$layer}")
        ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
}

foreach (['Actions', 'Models', 'Support'] as $layer) {
    arch("Telework {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Telework\\{$layer}")
        ->toOnlyBeUsedIn(['App\Modules\Telework', 'Database\Factories\Telework']);
}
