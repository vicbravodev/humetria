<?php

declare(strict_types=1);

use App\Modules\Results\Support\RiskLevels;

mutates(RiskLevels::class);

it('gives the five levels, from Nulo to Muy alto, each with its action word for word from Tablas 4 and 7 (C-15)', function (): void {
    // The DOF marks «evaluación específica» with its footnote 1, which the action leaves out.
    $dof = str_replace(['específica1', 'específicas1'], ['específica', 'específicas'], (string) file_get_contents(__DIR__.'/../../../docs/normas/nom-035/fuentes/dof-2018-10-23-nom-035.txt'));
    $catalog = RiskLevels::catalog();

    expect(array_column($catalog, 'value'))->toBe(['null', 'low', 'medium', 'high', 'very_high'])
        ->and(array_column($catalog, 'label'))->toBe(['Nulo o despreciable', 'Bajo', 'Medio', 'Alto', 'Muy alto']);

    foreach ($catalog as $level) {
        expect($dof)->toContain(" | {$level['action']}\n");
    }
});
