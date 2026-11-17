<?php

declare(strict_types=1);

/*
 * Adding or editing a record with several fields, with a list behind it, opens in the side panel (FormSheet), never in
 * a centered dialog: docs/design-system/componentes/Sheet.md. These forms already moved; none goes back to a dialog.
 */

dataset('record forms', [
    'organization/employee-sheet.tsx',
    'compensation/compensation-sheet.tsx',
    'interventions/action-sheet.tsx',
]);

it('keeps the form of a record in the side panel', function (string $file): void {
    $source = file_get_contents(__DIR__.'/../../resources/js/components/'.$file);

    expect($source)
        ->toBeString()
        ->toContain("@/components/humetria/form-sheet'")
        ->not->toContain('@/components/humetria/form-dialog')
        ->not->toContain('@/components/ui/dialog');
})->with('record forms');
