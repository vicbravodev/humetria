<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

/**
 * Who each row of an import reports to, from what its «jefe» cell says. The cell is read first as an employee number,
 * whatever its case, and only when nobody has that number and it has an @, as an email. Never as a name: numbers and
 * emails are unique in a company, so a cell names one person or none.
 */
final class ImportManagers
{
    /**
     * @param  list<array{row: int, key: string, number: string|null, email: string|null, managerText: string}>  $people  the file's valid rows: `row` as the spreadsheet numbers it, `key` the id of whom the row updates or `row:{n}` for a new person.
     * @param  array<string, array{number: string|null, email: string|null, manager: string|null, active: bool}>  $existing  the people the company has, by id. Of those in the file only `manager` and `active` count: the file says the rest.
     * @return array{managers: array<int, string|null>, errors: array<int, string>} by row: the manager's key (null for nobody), or why the cell cannot be used.
     */
    public static function resolve(array $people, array $existing): array
    {
        $rows = array_column($people, 'row', 'key');
        $directory = self::directory($people, $existing);
        $managers = [];
        $errors = [];

        foreach ($people as $person) {
            $text = trim($person['managerText']);
            $found = $text === '' ? null : self::find($directory, mb_strtolower($text));

            match (true) {
                $text === '' => $managers[$person['row']] = null,
                $found === null => $errors[$person['row']] = "No hay una persona con número o correo «{$text}» para ser su jefe.",
                $found === '' => $errors[$person['row']] = "«{$text}» está dado de baja y no puede ser jefe.",
                $found === $person['key'] => $errors[$person['row']] = 'Una persona no puede ser su propio jefe.',
                default => $managers[$person['row']] = $found,
            };
        }

        // The lines as the import would leave them: the file's replace the ones its people had; the rest stay.
        $lines = array_map(fn (array $known): ?string => $known['manager'], $existing);

        foreach (array_column($people, 'key', 'row') as $row => $key) {
            if (array_key_exists($row, $managers)) {
                $lines[$key] = $managers[$row];
            }
        }

        foreach ($people as $person) {
            $next = isset($managers[$person['row']]) ? self::cycle($person['key'], $lines, $rows) : null;

            if ($next !== null) {
                $errors[$person['row']] = "El jefe forma un ciclo con la fila {$next}.";
                unset($managers[$person['row']]);
            }
        }

        ksort($errors);

        return ['managers' => $managers, 'errors' => $errors];
    }

    /**
     * Whom a cell may name: by lowercase number and by email, the people of the file and the active ones outside it;
     * and apart, the numbers and emails of those who left, to say so.
     *
     * @param  list<array{row: int, key: string, number: string|null, email: string|null, managerText: string}>  $people
     * @param  array<string, array{number: string|null, email: string|null, manager: string|null, active: bool}>  $existing
     * @return array{number: array<string, string>, email: array<string, string>} the key by text; an empty key is someone who left.
     */
    private static function directory(array $people, array $existing): array
    {
        $directory = ['number' => [], 'email' => []];
        $entries = [];

        // The file's row of a person replaces what the company had of them.
        foreach (array_diff_key($existing, array_column($people, 'row', 'key')) as $id => $known) {
            $entries[] = [(string) $id, $known['number'], $known['email'], $known['active']];
        }

        foreach ($people as $person) {
            $entries[] = [$person['key'], $person['number'], $person['email'], $existing[$person['key']]['active'] ?? true];
        }

        foreach ($entries as [$key, $number, $email, $active]) {
            foreach (['number' => $number, 'email' => $email] as $kind => $value) {
                $text = mb_strtolower($value ?? '');

                // An active person is never hidden by one who left with the same text.
                if ($text !== '' && ($active || ! isset($directory[$kind][$text]))) {
                    $directory[$kind][$text] = $active ? $key : '';
                }
            }
        }

        return $directory;
    }

    /**
     * @param  array{number: array<string, string>, email: array<string, string>}  $directory
     * @return string|null the key of the person, an empty one for someone who left, or null for nobody.
     */
    private static function find(array $directory, string $text): ?string
    {
        $byNumber = $directory['number'][$text] ?? null;
        $byEmail = str_contains($text, '@') ? ($directory['email'][$text] ?? null) : null;

        // Somebody active, by number first; then somebody who left.
        return match (true) {
            $byNumber !== null && $byNumber !== '' => $byNumber,
            $byEmail !== null && $byEmail !== '' => $byEmail,
            default => $byNumber ?? $byEmail,
        };
    }

    /**
     * Walks up from the person. Null when the walk does not come back to them; otherwise the row of the next person
     * of the cycle the file has (their own when no other row is in it).
     *
     * ponytail: one walk per row, O(rows × depth); mark whole cycles at once if an import ever gets slow.
     *
     * @param  array<string, string|null>  $lines  the manager's key by person's key
     * @param  array<string, int>  $rows  the row by key, of the people in the file
     */
    private static function cycle(string $key, array $lines, array $rows): ?int
    {
        $seen = [$key => true];
        $next = null;

        for ($at = $lines[$key] ?? null; $at !== null && ! isset($seen[$at]); $at = $lines[$at] ?? null) {
            $seen[$at] = true;
            $next ??= $rows[$at] ?? null;
        }

        return $at === $key ? ($next ?? $rows[$key] ?? null) : null;
    }
}
