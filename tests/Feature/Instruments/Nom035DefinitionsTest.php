<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\SectionDefinition;
use App\Modules\Instruments\Support\InstrumentCatalog;

mutates(InstrumentCatalog::class);

/**
 * The part of the annex (docs/normas/nom-035/texto-de-referencia.md) that transcribes one guide, literal from the DOF.
 *
 * @return list<string>
 */
function annexGuide(string $roman): array
{
    $annex = (string) file_get_contents(__DIR__.'/../../../docs/normas/nom-035/texto-de-referencia.md');
    $parts = preg_split('/^## /m', $annex) ?: [];
    $part = collect($parts)->first(fn (string $part): bool => preg_match("/^Guía de referencia {$roman}\\b/", $part) === 1);

    return explode("\n", (string) $part);
}

/**
 * The guide's questions in the annex, by their number.
 *
 * @return array<int, string>
 */
function annexItems(string $roman): array
{
    $items = [];

    foreach (annexGuide($roman) as $line) {
        if (preg_match('/^(\d+)\. (.+)$/', $line, $match) === 1) {
            $items[(int) $match[1]] = $match[2];
        }
    }

    return $items;
}

/**
 * The instruction that opens each section of Guía II or III in the annex, in order.
 *
 * @return list<string>
 */
function annexInstructions(string $roman): array
{
    return collect(annexGuide($roman))
        ->filter(fn (string $line): bool => preg_match('/^\*\*(.+)\*\*$/', $line) === 1)
        ->map(fn (string $line): string => trim($line, '*'))
        ->values()
        ->all();
}

/** @return array<int, string> */
function definedItems(InstrumentDefinition $definition): array
{
    $items = [];

    foreach ($definition->sections as $section) {
        foreach ($section->items as $item) {
            $items[$item->number] = $item->text;
        }
    }

    return $items;
}

dataset('nom035 guides', [
    'Guía I' => ['nom035.guide-i', 'I', 20],
    'Guía II' => ['nom035.guide-ii', 'II', 46],
    'Guía III' => ['nom035.guide-iii', 'III', 72],
]);

it('has exactly the items of the DOF, literal and in order', function (string $key, string $roman, int $count): void {
    $definition = resolve(Instruments::class)->definition($key);

    expect($definition->itemNumbers())->toBe(range(1, $count))
        ->and(definedItems($definition))->toBe(annexItems($roman))
        ->and($definition->key)->toBe($key)
        ->and($definition->version)->toBe('dof-2018-10-23');
})->with('nom035 guides');

it('asks Guías II and III with the five frequency options, in the order of the norm', function (string $key): void {
    expect(resolve(Instruments::class)->definition($key)->options)->toBe([
        ['value' => 'always', 'label' => 'Siempre'],
        ['value' => 'almost_always', 'label' => 'Casi siempre'],
        ['value' => 'sometimes', 'label' => 'Algunas veces'],
        ['value' => 'almost_never', 'label' => 'Casi nunca'],
        ['value' => 'never', 'label' => 'Nunca'],
    ]);
})->with(['nom035.guide-ii', 'nom035.guide-iii']);

it('asks Guía I with yes or no', function (): void {
    expect(resolve(Instruments::class)->definition('nom035.guide-i')->options)->toBe([
        ['value' => 'yes', 'label' => 'Sí'],
        ['value' => 'no', 'label' => 'No'],
    ]);
});

it('opens each section of Guías II and III with the instruction the norm gives it', function (string $key, string $roman): void {
    $sections = resolve(Instruments::class)->definition($key)->sections;

    expect(array_map(fn (SectionDefinition $section): string => $section->instruction, $sections))->toBe(annexInstructions($roman))
        ->and(array_map(fn (SectionDefinition $section): ?string => $section->title, $sections))->each->toBeNull();
})->with([
    'Guía II' => ['nom035.guide-ii', 'II'],
    'Guía III' => ['nom035.guide-iii', 'III'],
]);

it('groups Guía I in its four sections, under their headings', function (): void {
    $sections = resolve(Instruments::class)->definition('nom035.guide-i')->sections;

    expect(array_map(fn (SectionDefinition $section): ?string => $section->title, $sections))->toBe([
        'I.- Acontecimiento traumático severo',
        'II.- Recuerdos persistentes sobre el acontecimiento (durante el último mes):',
        'III.- Esfuerzo por evitar circunstancias parecidas o asociadas al acontecimiento (durante el último mes):',
        'IV Afectación (durante el último mes):',
    ])
        ->and(array_map(fn (SectionDefinition $section): array => array_map(fn ($item): int => $item->number, $section->items), $sections))->toBe([
            range(1, 6), [7, 8], range(9, 15), range(16, 20),
        ])
        ->and($sections[0]->instruction)->toBe('¿Ha presenciado o sufrido alguna vez, durante o con motivo del trabajo un acontecimiento como los siguientes:');
});

it('asks the clients and supervision blocks only behind their yes or no question', function (string $key, array $clients, array $supervision): void {
    $gated = collect(resolve(Instruments::class)->definition($key)->sections)
        ->filter(fn (SectionDefinition $section): bool => $section->gate !== null)
        ->map(fn (SectionDefinition $section): array => [
            'gate' => $section->gate?->key,
            'question' => $section->gate?->question,
            'instruction' => $section->gate?->instruction,
            'items' => array_map(fn ($item): int => $item->number, $section->items),
        ])
        ->values()
        ->all();

    expect($gated)->toBe([
        [
            'gate' => 'serves_clients',
            'question' => 'En mi trabajo debo brindar servicio a clientes o usuarios:',
            'instruction' => 'Si su respuesta fue "SÍ", responda las preguntas siguientes. Si su respuesta fue "NO" pase a las preguntas de la sección siguiente.',
            'items' => $clients,
        ],
        [
            'gate' => 'supervises',
            'question' => 'Soy jefe de otros trabajadores:',
            'instruction' => 'Si su respuesta fue "SÍ", responda las preguntas siguientes. Si su respuesta fue "NO", ha concluido el cuestionario.',
            'items' => $supervision,
        ],
    ]);
})->with([
    'Guía II' => ['nom035.guide-ii', [41, 42, 43], [44, 45, 46]],
    'Guía III' => ['nom035.guide-iii', [65, 66, 67, 68], [69, 70, 71, 72]],
]);

it('identifies the person in Guía I only', function (string $key, bool $identified, string $title): void {
    $definition = resolve(Instruments::class)->definition($key);

    expect($definition->identified)->toBe($identified)
        ->and($definition->title)->toBe($title);
})->with([
    ['nom035.guide-i', true, 'Acontecimientos traumáticos severos'],
    ['nom035.guide-ii', false, 'Factores de riesgo psicosocial'],
    ['nom035.guide-iii', false, 'Factores de riesgo psicosocial y entorno organizacional'],
]);

it('knows no other instrument', function (): void {
    resolve(Instruments::class)->definition('nom035.guide-iv');
})->throws(InvalidArgumentException::class);
