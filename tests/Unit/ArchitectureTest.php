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

/**
 * The demo's story (database/seeders/Demo, spec 2026-11-16-demo-company §3) is no module: it tells each module's part through
 * that module's own actions, models and enums, as a person would, and nothing else of it (no HTTP, jobs, listeners or notifications).
 *
 * @return list<string>
 */
function demoStoryMayUse(string $internalNamespace): array
{
    return preg_match('/\\\\(Actions|Models|Enums)$/', $internalNamespace) === 1 ? ['Database\\Seeders\\Demo'] : [];
}

it('has the Attendance module registered in the architecture rules', function (): void {
    expect(modules())->toContain('Attendance');
});

it('has the Engagement module registered in the architecture rules', function (): void {
    expect(modules())->toContain('Engagement');
});

it('has the Notifications module registered in the architecture rules', function (): void {
    expect(modules())->toContain('Notifications');
});

// The push library has one door: no other module talks to it, so what leaves for a push service is always what Notifications builds.
arch('only Notifications talks to the push library')
    ->expect('Minishlink\WebPush')
    ->toOnlyBeUsedIn('App\Modules\Notifications');

// Humi never reads the comments of a climate survey: of Engagement, Assistant uses only the standing of each survey
// (states and counts of the whole company), never anything else of the module.
arch('Assistant uses of Engagement only its standing')
    ->expect('App\Modules\Assistant')
    ->not->toUse('App\Modules\Engagement')
    ->ignoring(['App\Modules\Engagement\Contracts\EngagementStanding', 'App\Modules\Engagement\Data\SurveyStanding']);

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
        // The module's factories (database/factories/{Module}, CLAUDE.md) build its models, so they count as inside it; the demo's
        // story may use its actions, models and enums (demoStoryMayUse).
        arch("{$internalNamespace} is internal to the {$module} module")
            ->expect($internalNamespace)
            ->toOnlyBeUsedIn(["App\\Modules\\{$module}", "Database\\Factories\\{$module}", ...demoStoryMayUse($internalNamespace)]);

        if (str_ends_with($internalNamespace, '\\Actions') || str_ends_with($internalNamespace, '\\Models')) {
            arch("{$internalNamespace} does not depend on HTTP")
                ->expect($internalNamespace)
                ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
        }
    }
}

// The fence of that exception: only the story itself and the binding of DemoWorkspace (spec §3) reach database/seeders/Demo.
arch('the demo story is used only by itself and the Demos providers')
    ->expect('Database\\Seeders\\Demo')
    ->toOnlyBeUsedIn(['Database\\Seeders\\Demo', 'App\\Modules\\Demos\\Providers']);

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
    // The demo's story uses its actions and models too (spec 2026-11-16-demo-company §3).
    arch("Telework {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Telework\\{$layer}")
        ->toOnlyBeUsedIn(['App\Modules\Telework', 'Database\Factories\Telework', ...demoStoryMayUse("App\\Modules\\Telework\\{$layer}")]);
}

/*
 * Seating (Ley Silla) keeps health data of the people who work standing, so, like Telework, its boundaries are spelled
 * out on their own. It reads the Comisión through Telework's public contract, never its internals.
 */
$seatingInternals = internalNamespacesOf('Seating');

it('has the Seating namespaces the architecture rules target', function () use ($seatingInternals): void {
    expect($seatingInternals)->toContain('App\Modules\Seating\Actions', 'App\Modules\Seating\Models', 'App\Modules\Seating\Support', 'App\Modules\Seating\Http');
});

foreach (['Actions', 'Models'] as $layer) {
    arch("Seating {$layer} do not depend on HTTP")
        ->expect("App\\Modules\\Seating\\{$layer}")
        ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
}

foreach (['Actions', 'Models', 'Support'] as $layer) {
    // The demo's story uses its actions and models too (spec 2026-11-16-demo-company §3).
    arch("Seating {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Seating\\{$layer}")
        ->toOnlyBeUsedIn(['App\Modules\Seating', 'Database\Factories\Seating', ...demoStoryMayUse("App\\Modules\\Seating\\{$layer}")]);
}

/*
 * Compensación handles what each person earns, so, like Seating, its boundaries are spelled out on their own. It reads the
 * roster through Organization's public contract, never its internals (the loop above already enforces that).
 */
$compensationInternals = internalNamespacesOf('Compensation');

it('has the Compensation namespaces the architecture rules target', function () use ($compensationInternals): void {
    expect($compensationInternals)->toContain('App\Modules\Compensation\Http', 'App\Modules\Compensation\Providers');
});

foreach (['Actions', 'Models'] as $layer) {
    arch("Compensation {$layer} do not depend on HTTP")
        ->expect("App\\Modules\\Compensation\\{$layer}")
        ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
}

foreach (['Actions', 'Models', 'Support'] as $layer) {
    // The demo's story uses its actions and models too (spec 2026-11-16-demo-company §3).
    arch("Compensation {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Compensation\\{$layer}")
        ->toOnlyBeUsedIn(['App\\Modules\\Compensation', 'Database\\Factories\\Compensation', ...demoStoryMayUse("App\\Modules\\Compensation\\{$layer}")]);
}

/*
 * Complaints (the NOM-035 channel for complaints and reports) holds the most delicate data of the platform: a report can be
 * against HR or the owner. Like Telework and Seating, its boundaries are spelled out on their own.
 */
$complaintsInternals = internalNamespacesOf('Complaints');

it('has the Complaints namespaces the architecture rules target', function () use ($complaintsInternals): void {
    expect($complaintsInternals)->toContain('App\Modules\Complaints\Actions', 'App\Modules\Complaints\Models', 'App\Modules\Complaints\Support', 'App\Modules\Complaints\Http');
});

foreach (['Actions', 'Models'] as $layer) {
    arch("Complaints {$layer} do not depend on HTTP")
        ->expect("App\\Modules\\Complaints\\{$layer}")
        ->not->toUse(['Illuminate\Http', 'Inertia', 'request', 'session', 'auth']);
}

foreach (['Actions', 'Models', 'Support'] as $layer) {
    // The demo's story uses its actions and models too (spec 2026-11-16-demo-company §3).
    arch("Complaints {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Complaints\\{$layer}")
        ->toOnlyBeUsedIn(['App\Modules\Complaints', 'Database\Factories\Complaints', ...demoStoryMayUse("App\\Modules\\Complaints\\{$layer}")]);
}

/*
 * Disclosure (the policy of prevention, its disclosure and the permanent page of the workers) writes the one page of the
 * platform that anybody with a link reads, so, like the modules above, its boundaries are spelled out on their own: it reads
 * the other modules only through their public contracts and data.
 */
$disclosureInternals = internalNamespacesOf('Disclosure');

it('has the Disclosure namespaces the architecture rules target', function () use ($disclosureInternals): void {
    expect($disclosureInternals)->toContain('App\Modules\Disclosure\Actions', 'App\Modules\Disclosure\Models', 'App\Modules\Disclosure\Support', 'App\Modules\Disclosure\Http');
});

foreach (['Actions', 'Models'] as $layer) {
    arch("Disclosure {$layer} do not depend on HTTP")
        ->expect("App\\Modules\\Disclosure\\{$layer}")
        ->not->toUse(['Illuminate\\Http', 'Inertia', 'request', 'session', 'auth']);
}

foreach (['Actions', 'Models', 'Support'] as $layer) {
    // The demo's story uses its actions and models too (spec 2026-11-16-demo-company §3).
    arch("Disclosure {$layer} are not used by the shared kernel")
        ->expect("App\\Modules\\Disclosure\\{$layer}")
        ->toOnlyBeUsedIn(['App\\Modules\\Disclosure', 'Database\\Factories\\Disclosure', ...demoStoryMayUse("App\\Modules\\Disclosure\\{$layer}")]);
}

/*
 * Time zones (spec 2026-11-17-timezones-design §4, phase 0): the registro de jornada measures each day in the zone of its work
 * center or of its mark, read through Tenancy's Timezones contract; the single zone of config/app.php is never read there.
 * The other modules still read it until their turn (phase 4); this list only shrinks.
 */
it('keeps the single display zone out of the modules that read zones per center', function (string $module): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../../app/Modules/{$module}", FilesystemIterator::SKIP_DOTS));
    $readers = [];

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php' && str_contains(file_get_contents($file->getPathname()) ?: '', 'display_timezone')) {
            $readers[] = $file->getFilename();
        }
    }

    expect($readers)->toBe([]);
})->with(['Attendance']);
