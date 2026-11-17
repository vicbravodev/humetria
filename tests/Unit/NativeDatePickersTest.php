<?php

declare(strict_types=1);

/*
 * Dates, months and times are typed or picked with the design system's pickers (DatePicker, DateRangePicker,
 * MonthPicker, TimePicker), never with the browser's own control: it looks different in each browser and language,
 * cannot be cleared the same way and does not read what a person pastes.
 */

const NATIVE_DATE_INPUT = '/\btype\s*[=:]\s*\{?\s*["\'`](?:date|month|time|week|datetime-local)["\'`]/';

it('recognises a native date, month or time control however it is written', function (string $source, bool $native): void {
    expect(preg_match(NATIVE_DATE_INPUT, $source) === 1)->toBe($native);
})->with([
    ['<Input type="date" />', true],
    ['<input type="month" name="period" />', true],
    ["<Input type={'time'} />", true],
    ["{ type: 'datetime-local' }", true],
    ['<input type="text" inputMode="numeric" />', false],
    ['<input type="hidden" name="date" />', false],
    ["const kind: 'date' | 'month' = 'date';", false],
]);

it('uses no native date, month or time control anywhere in the frontend', function (): void {
    $found = [];

    foreach (frontendSourceFiles() as $file) {
        if (preg_match('/\.(tsx?|jsx?)$/', $file->getFilename()) === 1
            && preg_match(NATIVE_DATE_INPUT, (string) file_get_contents((string) $file)) === 1) {
            $found[] = $file->getFilename();
        }
    }

    expect($found)->toBe([]);
});
