<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\RaiseText;

mutates(RaiseText::class);

/** @param  array<string, mixed>  $attributes */
function raiseOf(array $attributes): string
{
    return RaiseText::of(new Scenario($attributes));
}

it('says one raise for both groups when the trusted copy the unionized one, and two when they have their own', function (): void {
    expect(raiseOf(['group_changes' => ['unionized' => 575, 'trusted' => null]]))->toBe('5.75 % sindicalizados y confianza')
        ->and(raiseOf(['group_changes' => ['unionized' => 575, 'trusted' => 300]]))->toBe('5.75 % sindicalizados · 3 % confianza')
        ->and(raiseOf(['raise_unit' => 'amount', 'group_changes' => ['unionized' => 60_800, 'trusted' => null]]))->toBe('$608 al mes a sindicalizados y confianza');
});

it('names only the group of an average payroll, which has one', function (): void {
    $average = ['source' => 'average', 'average_group' => 'unionized'];

    expect(raiseOf([...$average, 'group_changes' => ['unionized' => 600, 'trusted' => null]]))->toBe('6 % sindicalizados')
        ->and(raiseOf([...$average, 'average_group' => 'trusted', 'group_changes' => ['unionized' => null, 'trusted' => 300]]))->toBe('3 % confianza')
        ->and(raiseOf([...$average, 'raise_unit' => 'amount', 'group_changes' => ['unionized' => 60_800, 'trusted' => null]]))->toBe('$608 al mes a sindicalizados');
});
