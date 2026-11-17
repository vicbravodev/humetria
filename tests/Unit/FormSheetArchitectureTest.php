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
    'tenancy/work-center-dialog.tsx',
    'tenancy/create-work-center-dialog.tsx',
    'leave/holiday-dialog.tsx',
    'leave/type-dialog.tsx',
    'engagement/commitments/commitment-dialog.tsx',
    'engagement/pulse/create-pulse-button.tsx',
    'engagement/pulse/schedule-dialog.tsx',
    'telework/add-workplace-dialog.tsx',
    'telework/equipment-dialog.tsx',
    'telework/training-dialog.tsx',
    'telework/join-roster-dialog.tsx',
    'interventions/add-row-dialog.tsx',
]);

it('keeps the form of a record, or its button, in the side panel', function (string $file): void {
    $source = file_get_contents(__DIR__.'/../../resources/js/components/'.$file);

    expect($source)
        ->toBeString()
        ->toMatch("~@/components/(humetria/form-sheet|ui/sheet)'~")
        ->not->toContain('@/components/humetria/form-dialog')
        ->not->toContain('@/components/ui/dialog');
})->with('record forms');
