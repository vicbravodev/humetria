<?php

declare(strict_types=1);

use App\Http\DocumentTitle;

it('puts the brand after the title of a page', function (): void {
    expect(DocumentTitle::for('Empleados', 'Humetría'))->toBe('Empleados - Humetría');
});

it('keeps a title that already starts with the brand as the whole title', function (): void {
    expect(DocumentTitle::for('Humetría: NOM-035 y más', 'Humetría'))->toBe('Humetría: NOM-035 y más');
});

it('does not take a title that only contains the brand for one that starts with it', function (): void {
    expect(DocumentTitle::for('Qué es Humetría', 'Humetría'))->toBe('Qué es Humetría - Humetría');
});
