<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/mutation-classes.php lists the classes the weekly mutation run passes to `pest --mutate --class`: every
 * class a test names in mutates(). composer mutate:critical then drops the thin ones (controllers, form requests,
 * notifications).
 */

const MUTATION_FIXTURES = 'tests/Fixtures/MutationSelection';

function mutatedClassesInFixtures(): string
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/mutation-classes.php', MUTATION_FIXTURES], dirname(__DIR__, 2));
    $process->mustRun();

    return trim($process->getOutput());
}

it('lists, sorted and once each, every class a test names in mutates()', function (): void {
    // InvoiceTest and LineTest both mutate Invoice, and InvoiceTest imports it under an alias.
    expect(mutatedClassesInFixtures())->toBe(implode("\n", [
        'App\Billing\Invoice',
        'App\Billing\InvoiceLine',
        'App\Billing\Refund',
        'App\Billing\Tax',
        'App\Modules\Shop\Actions\PlaceOrder',
        'App\Modules\Shop\Models\Order',
    ]));
});

it('leaves out the fixtures of other tests', function (): void {
    // Fixtures/IgnoredTest names a class no other test mutates, and PlainTest has no mutates() at all.
    expect(mutatedClassesInFixtures())->not->toContain('Ghost');
});
