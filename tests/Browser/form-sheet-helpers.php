<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;

/*
 * A form to add or edit a record opens in the side panel (FormSheet, docs/design-system/componentes/Sheet.md): never a
 * centered dialog, the whole width of a phone and 520 px from the tablet up, with the focus inside, and Escape closes it
 * and gives the focus back to the page.
 */

const FORM_SHEET = '[data-slot="sheet-content"]';

function assertFormSheet(AwaitableWebpage $page, bool $mobile): void
{
    $page
        ->assertPresent(FORM_SHEET)
        ->assertScript('() => document.querySelector(\'[data-slot="dialog-content"]\') === null', true)
        ->assertScript($mobile
            ? '() => Math.round(document.querySelector(\''.FORM_SHEET.'\').getBoundingClientRect().width) === 375'
            : '() => Math.round(document.querySelector(\''.FORM_SHEET.'\').getBoundingClientRect().width) === 520', true)
        ->assertScript('() => document.activeElement?.closest(\''.FORM_SHEET.'\') !== null', true)
        ->keys(':focus', 'Escape')
        ->assertScript(<<<'JS'
            () => new Promise((resolve) => {
                const start = performance.now();
                const frame = () => document.querySelector('[data-slot="sheet-content"]') === null
                    ? resolve(document.activeElement !== null && document.activeElement !== document.body)
                    : performance.now() - start > 2000 ? resolve(false) : requestAnimationFrame(frame);
                requestAnimationFrame(frame);
            })
            JS, true);
}
