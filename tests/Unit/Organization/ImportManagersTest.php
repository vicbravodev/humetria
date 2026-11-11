<?php

declare(strict_types=1);

use App\Modules\Organization\Support\ImportManagers;

mutates(ImportManagers::class);

/**
 * A row of the file: `row` as the spreadsheet numbers it, `key` the id of whom it updates or `row:{n}` for a new person.
 *
 * @return array{row: int, key: string, number: string|null, email: string|null, managerText: string}
 */
function importPerson(int $row, string $managerText, ?string $number = null, ?string $email = null, ?string $key = null): array
{
    return ['row' => $row, 'key' => $key ?? "row:{$row}", 'number' => $number, 'email' => $email, 'managerText' => $managerText];
}

/**
 * A person the company already has.
 *
 * @return array{number: string|null, email: string|null, manager: string|null, active: bool}
 */
function known(?string $number = null, ?string $email = null, ?string $manager = null, bool $active = true): array
{
    return ['number' => $number, 'email' => $email, 'manager' => $manager, 'active' => $active];
}

it('finds the manager by employee number, whatever its case, among the file and the people the company has', function (): void {
    $resolved = ImportManagers::resolve([
        importPerson(2, 'a-001', 'B-002'),
        importPerson(3, ' x-9 ', 'C-003'),
        importPerson(4, '', 'A-001'),
    ], ['boss' => known('X-9')]);

    expect($resolved)->toBe(['managers' => [2 => 'row:4', 3 => 'boss', 4 => null], 'errors' => []]);
});

it('finds the manager by email only when no number is that text', function (): void {
    $resolved = ImportManagers::resolve([
        importPerson(2, 'JEFA@acme.mx', 'A-1'),
        importPerson(3, 'otra@acme.mx', 'A-2'),
        // A new person the file identifies by email alone.
        importPerson(4, '', null, 'jefa@acme.mx'),
        // Somebody's number is that same text: the number wins.
        importPerson(5, '', 'otra@acme.mx'),
    ], ['other' => known(null, 'otra@acme.mx')]);

    expect($resolved)->toBe(['managers' => [2 => 'row:4', 3 => 'row:5', 4 => null, 5 => null], 'errors' => []]);
});

it('never takes for an email a text without an at sign', function (): void {
    $resolved = ImportManagers::resolve([importPerson(2, 'jefa', 'A-1')], ['boss' => known(null, 'jefa')]);

    expect($resolved['errors'])->toBe([2 => 'No hay una persona con número o correo «jefa» para ser su jefe.']);
});

it('uses what the file says of a person it updates, not what they had', function (): void {
    // The file takes the number from Old and leaves them with an email only.
    $resolved = ImportManagers::resolve([
        importPerson(2, 'N-1', null, 'old@acme.mx', 'old'),
        importPerson(3, 'old@acme.mx', 'A-2'),
    ], ['old' => known('N-1', 'viejo@acme.mx')]);

    expect($resolved['managers'][3])->toBe('old')
        ->and($resolved['errors'])->toBe([2 => 'No hay una persona con número o correo «N-1» para ser su jefe.']);
});

it('rejects a manager nobody is, one who left and the person themselves', function (): void {
    $resolved = ImportManagers::resolve([
        importPerson(2, 'Z-404', 'A-1'),
        importPerson(3, 'B-9', 'A-2'),
        importPerson(4, 'fue@acme.mx', 'A-3'),
        importPerson(5, 'a-4', 'A-4'),
        // Whoever left stays out even when the file names them.
        importPerson(6, '', 'G-1', null, 'gone-in-file'),
        importPerson(7, 'G-1', 'A-5'),
    ], ['gone' => known('B-9', 'fue@acme.mx', null, false), 'gone-in-file' => known('G-1', null, null, false)]);

    expect($resolved['errors'])->toBe([
        2 => 'No hay una persona con número o correo «Z-404» para ser su jefe.',
        3 => '«B-9» está dado de baja y no puede ser jefe.',
        4 => '«fue@acme.mx» está dado de baja y no puede ser jefe.',
        5 => 'Una persona no puede ser su propio jefe.',
        7 => '«G-1» está dado de baja y no puede ser jefe.',
    ])->and($resolved['managers'])->toBe([6 => null]);
});

it('rejects a cycle inside the file, naming in each row the next one', function (): void {
    $resolved = ImportManagers::resolve([
        importPerson(2, 'A-2', 'A-1'),
        importPerson(3, 'A-3', 'A-2'),
        importPerson(4, 'A-1', 'A-3'),
        importPerson(5, 'A-1', 'A-4'),
    ], []);

    expect($resolved['errors'])->toBe([
        2 => 'El jefe forma un ciclo con la fila 3.',
        3 => 'El jefe forma un ciclo con la fila 4.',
        4 => 'El jefe forma un ciclo con la fila 2.',
    ])->and($resolved['managers'])->toBe([5 => 'row:2']);
});

it('lets two people swap places: only the final lines count', function (): void {
    // Ana reported to Beto; the file puts Beto under Ana and Ana under nobody's old line.
    $resolved = ImportManagers::resolve([
        importPerson(2, 'B-1', 'A-1', null, 'ana'),
        importPerson(3, '', 'B-1', null, 'beto'),
    ], ['ana' => known('A-1'), 'beto' => known('B-1', null, 'ana')]);

    expect($resolved)->toBe(['managers' => [2 => 'beto', 3 => null], 'errors' => []]);

    $swapped = ImportManagers::resolve([
        importPerson(2, 'B-1', 'A-1', null, 'ana'),
        importPerson(3, 'C-1', 'B-1', null, 'beto'),
    ], ['ana' => known('A-1', null, 'carla'), 'beto' => known('B-1', null, 'ana'), 'carla' => known('C-1')]);

    expect($swapped)->toBe(['managers' => [2 => 'beto', 3 => 'carla'], 'errors' => []]);
});

it('rejects a cycle that a line the file does not touch closes', function (): void {
    // Carla already reports to Ana and is not in the file, which puts Ana under Beto and Beto under Carla.
    $resolved = ImportManagers::resolve([
        importPerson(2, 'B-1', 'A-1', null, 'ana'),
        importPerson(3, 'C-1', 'B-1', null, 'beto'),
    ], ['ana' => known('A-1'), 'beto' => known('B-1'), 'carla' => known('C-1', null, 'ana')]);

    expect($resolved['errors'])->toBe([
        2 => 'El jefe forma un ciclo con la fila 3.',
        3 => 'El jefe forma un ciclo con la fila 2.',
    ]);
});

it('ends on a cycle the company already had, without blaming who only hangs from it', function (): void {
    $resolved = ImportManagers::resolve(
        [importPerson(2, 'X-1', 'A-1')],
        ['x' => known('X-1', null, 'y'), 'y' => known('Y-1', null, 'x')],
    );

    expect($resolved)->toBe(['managers' => [2 => 'x'], 'errors' => []]);
});
