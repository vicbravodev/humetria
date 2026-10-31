<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/mutation-classes.php picks the classes a CI shard passes to `pest --mutate --class`. Each shard runs every
 * mutation test (--group=__pest_mutate_only), as the full run does, so a class gets the same result in any shard.
 */

const MUTATION_FIXTURES = 'tests/Fixtures/MutationSelection';

const EVERY_MUTATED_CLASS = [
    'App\Billing\Invoice',
    'App\Billing\InvoiceLine',
    'App\Billing\Refund',
    'App\Billing\Tax',
    'App\Modules\Shop\Actions\PlaceOrder',
    'App\Modules\Shop\Models\Order',
];

/**
 * @param  list<string>  $changed
 * @param  list<string>  $options
 */
function mutatedClassesFor(array $changed, array $options = []): string
{
    $script = dirname(__DIR__, 2).'/scripts/mutation-classes.php';
    $process = new Process([PHP_BINARY, $script, ...$options, MUTATION_FIXTURES, MUTATION_FIXTURES.'/app'], dirname(__DIR__, 2));
    $process->setInput(implode("\n", $changed)."\n");
    $process->mustRun();

    return trim($process->getOutput());
}

it('mutates the classes of every test file that mutates a changed class', function (): void {
    expect(mutatedClassesFor(['app/Modules/Shop/Models/Order.php']))->toBe(implode("\n", [
        'App\Billing\Invoice',
        'App\Modules\Shop\Actions\PlaceOrder',
        'App\Modules\Shop\Models\Order',
    ]));
});

it('leaves out the classes of test files that do not mutate the changed class', function (): void {
    expect(mutatedClassesFor(['app/Modules/Shop/Actions/PlaceOrder.php']))->toBe(implode("\n", [
        'App\Modules\Shop\Actions\PlaceOrder',
        'App\Modules\Shop\Models\Order',
    ]));
});

it('resolves a class imported under an alias', function (): void {
    expect(mutatedClassesFor(['app/Billing/Refund.php', 'app/Modules/Shop/Actions/PlaceOrder.php']))->toBe(implode("\n", [
        'App\Billing\Refund',
        'App\Modules\Shop\Actions\PlaceOrder',
        'App\Modules\Shop\Models\Order',
    ]))->and(mutatedClassesFor(['tests/Fixtures/MutationSelection/Feature/InvoiceTest.php']))->toBe(implode("\n", [
        'App\Billing\Invoice',
        'App\Modules\Shop\Models\Order',
    ]));
});

it('follows Pest in mutating, for a class, every class of its namespace that starts with its name', function (): void {
    // When InvoiceTest mutates Invoice, Pest mutates InvoiceLine too, so a change to InvoiceLine reruns InvoiceTest.
    expect(mutatedClassesFor(['app/Billing/InvoiceLine.php']))->toBe(implode("\n", [
        'App\Billing\Invoice',
        'App\Billing\InvoiceLine',
        'App\Modules\Shop\Models\Order',
    ]));
});

it('mutates nothing when the diff touches no mutated class', function (array $changed): void {
    expect(mutatedClassesFor($changed))->toBe('');
})->with([
    'frontend and docs' => [['resources/js/pages/landing.tsx', 'docs/notes.md']],
    'a class no test mutates' => [['app/Modules/Shop/Support/Money.php']],
    'a test without mutates()' => [['tests/Fixtures/MutationSelection/Unit/PlainTest.php']],
    'a fixture of another test' => [['tests/Fixtures/MutationSelection/Fixtures/IgnoredTest.php']],
    'a deleted file' => [['app/Modules/Shop/Gone.php']],
]);

it('mutates every class with --all', function (): void {
    expect(mutatedClassesFor([], ['--all']))->toBe(implode("\n", EVERY_MUTATED_CLASS));
});

it('mutates every class when the diff touches what every test depends on', function (string $changed): void {
    expect(mutatedClassesFor(['app/Modules/Shop/Models/Order.php', $changed]))->toBe(implode("\n", EVERY_MUTATED_CLASS));
})->with([
    'a migration' => 'database/migrations/2026_01_01_000000_create_orders_table.php',
    'the dependencies' => 'composer.lock',
    'the test bootstrap' => 'tests/Pest.php',
    'the base test case' => 'tests/TestCase.php',
    'the PHPUnit configuration' => 'phpunit.xml',
    'a config file' => 'config/app.php',
    'a route file' => 'routes/web.php',
    'a translation' => 'lang/es/validation.php',
    'a Blade view' => 'resources/views/app.blade.php',
    'the npm dependencies' => 'package.json',
    'the npm lockfile' => 'package-lock.json',
    'a script' => 'scripts/mutation-score.php',
]);

it('keeps in one shard the classes Pest mutates together, balanced by the size of what it mutates', function (): void {
    // Refund weighs more than Invoice, InvoiceLine, Order and PlaceOrder together; Tax has no source and weighs nothing.
    expect(mutatedClassesFor([], ['--all', '--shard=1/2']))->toBe('App\Billing\Refund')
        ->and(mutatedClassesFor([], ['--all', '--shard=2/2']))->toBe(implode("\n", [
            'App\Billing\Invoice',
            'App\Billing\InvoiceLine',
            'App\Billing\Tax',
            'App\Modules\Shop\Actions\PlaceOrder',
            'App\Modules\Shop\Models\Order',
        ]));
});

it('puts every class in exactly one shard', function (): void {
    $shards = array_map(
        fn (int $shard): array => array_filter(explode("\n", mutatedClassesFor([], ['--all', "--shard={$shard}/3"]))),
        [1, 2, 3],
    );

    $classes = array_merge(...$shards);
    sort($classes);

    expect($classes)->toBe(EVERY_MUTATED_CLASS);
});

it('leaves a shard empty when there are more shards than groups', function (): void {
    expect(mutatedClassesFor([], ['--all', '--shard=4/4']))->toBe('');
});

it('shards only what the diff selected', function (): void {
    expect(mutatedClassesFor(['app/Billing/Refund.php'], ['--shard=1/2']))->toBe('App\Billing\Refund')
        ->and(mutatedClassesFor(['app/Billing/Refund.php'], ['--shard=2/2']))->toBe('');
});
