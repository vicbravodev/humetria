<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Organization\Contracts\CompensationRoster;

/**
 * Who an individual salary may be given to: a person of the account that is active and can be costed (category and level,
 * hire date and group), found by the company's employee number ignoring case. The roster is read only through its contract,
 * by number, so no name, contact or internal id gets here.
 */
final readonly class OverrideTargets
{
    public function __construct(private CompensationRoster $roster) {}

    /**
     * The employee numbers as the roster stores them, and what is wrong with the ones it cannot give a salary to.
     *
     * @param  list<string>  $numbers
     * @return array{found: array<string, string>, problems: array<string, string>} both by lower-case number; the first
     *                                                                              gives the number as the roster has it
     */
    public function resolve(array $numbers): array
    {
        $people = $this->roster->individuals($numbers);
        $found = [];

        foreach ($people as $number => $person) {
            $found[mb_strtolower($number)] = $person->employeeNumber;
        }

        $lost = array_values(array_unique(array_filter(array_map(mb_strtolower(...), $numbers), fn (string $number): bool => ! isset($found[$number]))));
        $incomplete = $lost === [] ? [] : array_map(mb_strtolower(...), $this->roster->gaps()->missing);
        $problems = [];

        foreach ($lost as $number) {
            $problems[$number] = in_array($number, $incomplete, true)
                ? 'A esta persona le falta categoría y nivel, fecha de ingreso o grupo en el padrón: complétalos primero.'
                : 'No hay una persona activa con este ID de la empresa.';
        }

        return ['found' => $found, 'problems' => $problems];
    }
}
