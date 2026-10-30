<?php

declare(strict_types=1);

use App\Modules\Instruments\Support\Nom035\GuideTwo;
use App\Modules\Responses\Data\AnonymousRecord;
use App\Modules\Results\Support\RiskDistribution;

mutates(RiskDistribution::class);

const AREA_A = '00000000-0000-4000-8000-00000000000a';
const AREA_B = '00000000-0000-4000-8000-00000000000b';
const AREA_C = '00000000-0000-4000-8000-00000000000c';

/** A Guía II where every item scores 0 (Nulo everywhere), and no one served clients or supervised. */
function calmGuideTwo(): array
{
    $answers = nom035Answers('nom035.guide-ii', 'never', clients: false, supervises: false);

    foreach (GuideTwo::scoring()->zeroOnAlways as $item) {
        $answers[(string) $item] = 'always';
    }

    return $answers;
}

/**
 * Guía II records of one area: by final level, "never" is Medio, "almost_never" Alto and "always" Muy alto (gates
 * answered "yes"), and calm ones are Nulo.
 *
 * @return list<AnonymousRecord>
 */
function guideTwoRecords(string $area, int $calm = 0, int $never = 0, int $almostNever = 0, int $always = 0): array
{
    $records = [];

    foreach ([[$calm, calmGuideTwo()], [$never, nom035Answers('nom035.guide-ii', 'never')], [$almostNever, nom035Answers('nom035.guide-ii', 'almost_never')], [$always, nom035Answers('nom035.guide-ii', 'always')]] as [$count, $answers]) {
        for ($index = 0; $index < $count; $index++) {
            $records[] = new AnonymousRecord($area, 'dof-2018-10-23', $answers);
        }
    }

    return $records;
}

/** @param array<string, mixed> $result */
function levelsOf(array $result, string $group, string $key): array
{
    return collect($result[$group])->firstWhere('key', $key)['levels'];
}

beforeEach(function (): void {
    config(['results.min_group' => 5]);
    $this->distribution = resolve(RiskDistribution::class);
});

it('counts how many questionnaires fall in each risk level, never averaging them', function (): void {
    $records = guideTwoRecords(AREA_A, calm: 2, never: 1, almostNever: 3, always: 1);

    $result = $this->distribution->of('nom035.guide-ii', $records);

    expect($result['answered'])->toBe(7)
        ->and($result['visible'])->toBeTrue()
        // Nulo, Bajo, Medio, Alto, Muy alto.
        ->and($result['final'])->toBe([2, 0, 1, 3, 1])
        ->and(levelsOf($result, 'categories', 'work_environment'))->toBe([3, 3, 0, 0, 1])
        ->and(levelsOf($result, 'categories', 'leadership_relations'))->toBe([2, 0, 0, 4, 1])
        ->and(collect($result['categories'])->pluck('label')->all())->toBe(['Ambiente de trabajo', 'Factores propios de la actividad', 'Organización del tiempo de trabajo', 'Liderazgo y relaciones en el trabajo'])
        ->and(collect($result['domains'])->pluck('key')->all())->toContain('workload', 'violence')
        ->and(array_sum(levelsOf($result, 'domains', 'violence')))->toBe(7)
        // Each domain names its category, so a report can nest them; a category names none.
        ->and(collect($result['domains'])->firstWhere('key', 'violence')['category'])->toBe('leadership_relations')
        ->and(collect($result['domains'])->firstWhere('key', 'workload')['category'])->toBe('activity_factors')
        ->and(collect($result['categories'])->pluck('category')->unique()->all())->toBe([null]);
});

it('shows a group of 5 questionnaires, and none of 4', function (int $answered, bool $visible): void {
    $result = $this->distribution->of('nom035.guide-ii', guideTwoRecords(AREA_A, never: $answered));

    expect($result['answered'])->toBe($answered)
        ->and($result['visible'])->toBe($visible)
        ->and($result['final'])->toBe($visible ? [0, 0, $answered, 0, 0] : [])
        ->and($result['categories'])->toHaveCount($visible ? 4 : 0)
        ->and($result['domains'])->toHaveCount($visible ? 8 : 0);
})->with([[4, false], [5, true], [0, false]]);

it('uses a minimum group size from configuration', function (): void {
    config(['results.min_group' => 8]);

    expect(resolve(RiskDistribution::class)->of('nom035.guide-ii', guideTwoRecords(AREA_A, never: 7))['visible'])->toBeFalse()
        ->and(resolve(RiskDistribution::class)->of('nom035.guide-ii', guideTwoRecords(AREA_A, never: 8))['visible'])->toBeTrue();
});

it('shows each area of at least 5, by final level', function (): void {
    $records = [...guideTwoRecords(AREA_A, never: 5), ...guideTwoRecords(AREA_B, calm: 1, always: 5)];

    expect($this->distribution->areas('nom035.guide-ii', $records))->toBe([
        'visible' => [
            ['areaId' => AREA_B, 'answered' => 6, 'final' => [1, 0, 0, 0, 5]],
            ['areaId' => AREA_A, 'answered' => 5, 'final' => [0, 0, 5, 0, 0]],
        ],
        'hidden' => 0,
    ]);
});

it('hides more areas when the hidden ones would add up to fewer than 5, or the center minus the rest would reveal them', function (array $sizes, array $visible, int $hidden): void {
    $records = [];

    foreach ($sizes as $area => $count) {
        $records = [...$records, ...guideTwoRecords($area, never: $count)];
    }

    $areas = $this->distribution->areas('nom035.guide-ii', $records);

    expect(array_column($areas['visible'], 'areaId'))->toBe($visible)
        ->and($areas['hidden'])->toBe($hidden);
})->with([
    'one small area hides the next smallest' => [[AREA_A => 10, AREA_B => 2], [], 2],
    'two small areas that add up to 5 are hidden alone' => [[AREA_A => 6, AREA_B => 3, AREA_C => 2], [AREA_A], 2],
    'the smallest visible area goes first' => [[AREA_A => 9, AREA_B => 5, AREA_C => 2], [AREA_A], 2],
    'the hidden ones reach 5 by themselves' => [[AREA_A => 6, AREA_B => 4, AREA_C => 4], [AREA_A], 2],
    'every area of at least 5' => [[AREA_A => 5, AREA_B => 5], [AREA_A, AREA_B], 0],
    'a tie among the smallest goes by id' => [[AREA_A => 5, AREA_B => 5, AREA_C => 1], [AREA_B], 2],
]);

it('filters by an area it shows, and ignores an area it hides', function (): void {
    $records = [...guideTwoRecords(AREA_A, always: 6), ...guideTwoRecords(AREA_B, never: 5), ...guideTwoRecords(AREA_C, calm: 3)];

    $inA = $this->distribution->of('nom035.guide-ii', $records, AREA_A);
    $inC = $this->distribution->of('nom035.guide-ii', $records, AREA_C);

    // C (3) is hidden, and with it B (5), the smallest shown: A stays.
    expect($inA['answered'])->toBe(6)
        ->and($inA['final'])->toBe([0, 0, 0, 0, 6])
        ->and($this->distribution->of('nom035.guide-ii', $records, AREA_B)['answered'])->toBe(14)
        ->and($inC['answered'])->toBe(14)
        ->and($inC['final'])->toBe([3, 0, 5, 0, 6]);
});

it('shows how each question was answered, and only a question answered by at least 5', function (): void {
    $records = [...guideTwoRecords(AREA_A, never: 4, calm: 3), ...guideTwoRecords(AREA_B, always: 1)];

    $sections = $this->distribution->items('nom035.guide-ii', $records);
    $items = collect($sections)->flatMap(fn (array $section): array => $section['items'])->keyBy('number');

    expect($sections[0]['instruction'])->toBe('Para responder las preguntas siguientes considere las condiciones de su centro de trabajo, así como la cantidad y ritmo de trabajo.')
        // Unfavorable: answered on the side that scores 3 or 4 points; on item 1 that is "Siempre".
        ->and($items[1])->toBe(['number' => 1, 'text' => 'Mi trabajo me exige hacer mucho esfuerzo físico', 'answered' => 8, 'counts' => [1, 0, 0, 0, 7], 'unfavorable' => 1])
        // Item 18 scores 0 on "Siempre": the calm ones answered it so, and the unfavorable side is "Nunca".
        ->and($items[18]['counts'])->toBe([4, 0, 0, 0, 4])
        ->and($items[18]['unfavorable'])->toBe(4)
        // Clients: only those who answered "yes" to the gate, 5 of 8.
        ->and($items[41])->toBe(['number' => 41, 'text' => $items[41]['text'], 'answered' => 5, 'counts' => [1, 0, 0, 0, 4], 'unfavorable' => 1])
        ->and($items)->toHaveCount(46);

    $fewer = collect($this->distribution->items('nom035.guide-ii', [...guideTwoRecords(AREA_A, never: 3, calm: 3), ...guideTwoRecords(AREA_B, always: 1)]))
        ->flatMap(fn (array $section): array => $section['items'])->keyBy('number');

    expect($fewer[41])->toBe(['number' => 41, 'text' => $items[41]['text'], 'answered' => 4, 'counts' => null, 'unfavorable' => null])
        ->and($fewer[1]['counts'])->toBe([1, 0, 0, 0, 6]);
});

it('shows no question of a group under the minimum', function (): void {
    $items = collect($this->distribution->items('nom035.guide-ii', guideTwoRecords(AREA_A, never: 4)))->flatMap(fn (array $section): array => $section['items']);

    expect($items->pluck('counts')->filter()->all())->toBe([])
        ->and($items->pluck('unfavorable')->filter()->all())->toBe([]);
});
