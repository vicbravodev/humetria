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

/*
 * `--plan` splits the run by module (the namespace under App\Modules\, or Kernel for the rest). Pest measures coverage
 * with every test it runs and hands each mutant's process one --filter alternative per covering test: a line the
 * whole suite touches overflows the OS argument limit (128 KB). So each module runs only the test files that mutate
 * its classes.
 */

const MUTATION_PLAN_FIXTURES = 'tests/Fixtures/MutationPlan';

/**
 * @return array<string, array{classes: list<string>, filter: string}>
 */
function mutationPlanInFixtures(): array
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/mutation-classes.php', MUTATION_PLAN_FIXTURES, '--plan'], dirname(__DIR__, 2));
    $process->mustRun();

    $plan = [];

    foreach (array_filter(explode("\n", $process->getOutput())) as $line) {
        [$module, $classes, $filter] = explode("\t", $line);
        $plan[$module] = ['classes' => explode(',', $classes), 'filter' => $filter];
    }

    return $plan;
}

function planRunsTest(string $filter, string $testClass): bool
{
    // PHPUnit wraps a bare --filter in /.../i and matches it against the test id Pest builds: P\Tests\...\FooTest::name.
    return preg_match('/'.str_replace('/', '\/', $filter).'/i', 'P\\'.$testClass.'::__pest_evaluable_it_does_something') === 1;
}

it('plans one run per module, Kernel for what is outside App\Modules, sorted, with its classes', function (): void {
    $plan = mutationPlanInFixtures();

    expect(array_keys($plan))->toBe(['Kernel', 'Shop'])
        ->and($plan['Kernel']['classes'])->toBe(['App\Billing\Invoice', 'App\Logging\Audit'])
        ->and($plan['Shop']['classes'])->toBe(['App\Modules\Shop\Actions\PlaceOrder', 'App\Modules\Shop\Models\Order']);
});

it('leaves controllers, form requests and notifications out of the plan, and a module that only has those', function (): void {
    $plan = mutationPlanInFixtures();

    expect($plan)->not->toHaveKey('Mail')
        ->and(implode(',', $plan['Shop']['classes']))->not->toContain('OrderController');
});

it('runs in a module only the test files that mutate its classes', function (): void {
    $plan = mutationPlanInFixtures();
    $feature = 'Tests\Fixtures\MutationPlan\Feature\\';

    expect(planRunsTest($plan['Shop']['filter'], $feature.'ShopOrderTest'))->toBeTrue()
        ->and(planRunsTest($plan['Shop']['filter'], $feature.'ShopCheckoutTest'))->toBeTrue()
        ->and(planRunsTest($plan['Shop']['filter'], $feature.'AuditTest'))->toBeFalse()
        ->and(planRunsTest($plan['Shop']['filter'], $feature.'OrderPageTest'))->toBeFalse()
        ->and(planRunsTest($plan['Shop']['filter'], $feature.'NewsletterTest'))->toBeFalse()
        ->and(planRunsTest($plan['Kernel']['filter'], $feature.'AuditTest'))->toBeTrue()
        ->and(planRunsTest($plan['Kernel']['filter'], $feature.'ShopCheckoutTest'))->toBeTrue()
        ->and(planRunsTest($plan['Kernel']['filter'], $feature.'ShopOrderTest'))->toBeFalse();
});

it('does not mistake a test whose name only ends like another one', function (): void {
    $plan = mutationPlanInFixtures();

    expect(planRunsTest($plan['Shop']['filter'], 'Tests\Fixtures\MutationPlan\Feature\OtherShopOrderTest'))->toBeFalse()
        ->and(planRunsTest($plan['Shop']['filter'], 'Tests\Fixtures\MutationPlan\Feature\ShopOrderTestExtra'))->toBeFalse();
});

it('leaves out the fixtures of other tests in the plan too', function (): void {
    $plan = mutationPlanInFixtures();

    expect(implode(',', $plan['Kernel']['classes']))->not->toContain('Ghost');
});
