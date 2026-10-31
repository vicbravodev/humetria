<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\GroupScore;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\RiskScore;
use App\Modules\Instruments\Support\Nom035\GuideThree;
use App\Modules\Instruments\Support\Nom035\GuideTwo;
use App\Modules\Instruments\Support\RiskScorer;

mutates(RiskScorer::class, RiskLevel::class, GuideTwo::class, GuideThree::class);

/*
 * Everything below is typed from docs/normas/nom-035/texto-de-referencia.md (Tablas 2, 3, 5 and 6 and the ranges of
 * II.3 c and III.3 c), never read from the production code: the test is the second, independent transcription.
 */

/** Items that score 0 on "Siempre" and 4 on "Nunca" (Tablas 2 and 5); every other item scores the other way round. */
const ZERO_ON_ALWAYS = [
    'nom035.guide-ii' => [18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33],
    'nom035.guide-iii' => [1, 4, 23, 24, 25, 26, 27, 28, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 55, 56, 57],
];

/** Tablas 3 and 6: the items of each domain, and the domains of each category. */
const DOMAIN_ITEMS = [
    'nom035.guide-ii' => [
        'environment_conditions' => [1, 2, 3],
        'workload' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 41, 42, 43],
        'lack_of_control' => [18, 19, 20, 21, 22, 26, 27],
        'work_day' => [14, 15],
        'work_family' => [16, 17],
        'leadership' => [23, 24, 25, 28, 29],
        'relations' => [30, 31, 32, 44, 45, 46],
        'violence' => [33, 34, 35, 36, 37, 38, 39, 40],
    ],
    'nom035.guide-iii' => [
        'environment_conditions' => [1, 2, 3, 4, 5],
        'workload' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 65, 66, 67, 68],
        'lack_of_control' => [23, 24, 25, 26, 27, 28, 29, 30, 35, 36],
        'work_day' => [17, 18],
        'work_family' => [19, 20, 21, 22],
        'leadership' => [31, 32, 33, 34, 37, 38, 39, 40, 41],
        'relations' => [42, 43, 44, 45, 46, 69, 70, 71, 72],
        'violence' => [57, 58, 59, 60, 61, 62, 63, 64],
        'performance_recognition' => [47, 48, 49, 50, 51, 52],
        'belonging_instability' => [53, 54, 55, 56],
    ],
];

const CATEGORY_DOMAINS = [
    'work_environment' => ['environment_conditions'],
    'activity_factors' => ['workload', 'lack_of_control'],
    'work_time' => ['work_day', 'work_family'],
    'leadership_relations' => ['leadership', 'relations', 'violence'],
    'organizational_environment' => ['performance_recognition', 'belonging_instability'],
];

/** II.3 c and III.3 c: the lower bound of Bajo, Medio, Alto and Muy alto (each inclusive). */
const CUTOFFS = [
    'nom035.guide-ii' => [
        'final' => [20, 45, 70, 90],
        'work_environment' => [3, 5, 7, 9],
        'activity_factors' => [10, 20, 30, 40],
        'work_time' => [4, 6, 9, 12],
        'leadership_relations' => [10, 18, 28, 38],
        'environment_conditions' => [3, 5, 7, 9],
        'workload' => [12, 16, 20, 24],
        'lack_of_control' => [5, 8, 11, 14],
        'work_day' => [1, 2, 4, 6],
        'work_family' => [1, 2, 4, 6],
        'leadership' => [3, 5, 8, 11],
        'relations' => [5, 8, 11, 14],
        'violence' => [7, 10, 13, 16],
    ],
    'nom035.guide-iii' => [
        'final' => [50, 75, 99, 140],
        'work_environment' => [5, 9, 11, 14],
        'activity_factors' => [15, 30, 45, 60],
        'work_time' => [5, 7, 10, 13],
        'leadership_relations' => [14, 29, 42, 58],
        'organizational_environment' => [10, 14, 18, 23],
        'environment_conditions' => [5, 9, 11, 14],
        'workload' => [15, 21, 27, 37],
        'lack_of_control' => [11, 16, 21, 25],
        'work_day' => [1, 2, 4, 6],
        'work_family' => [4, 6, 8, 10],
        'leadership' => [9, 12, 16, 20],
        'relations' => [10, 13, 17, 21],
        'violence' => [7, 10, 13, 16],
        'performance_recognition' => [6, 10, 14, 18],
        'belonging_instability' => [4, 6, 8, 10],
    ],
];

const LEVELS = [RiskLevel::Null, RiskLevel::Low, RiskLevel::Medium, RiskLevel::High, RiskLevel::VeryHigh];

/** What an item scores for an answer, by Tablas 2 and 5. */
function itemScore(string $key, int $item, string $value): int
{
    $position = array_search($value, ['always', 'almost_always', 'sometimes', 'almost_never', 'never'], true);

    return in_array($item, ZERO_ON_ALWAYS[$key], true) ? (int) $position : 4 - (int) $position;
}

/** A complete questionnaire (both blocks asked) where every item scores 0. */
function zeroAnswers(string $key): array
{
    $answers = nom035Answers($key);

    foreach ($answers as $item => $value) {
        if (is_int($item)) {
            $answers[$item] = in_array($item, ZERO_ON_ALWAYS[$key], true) ? 'always' : 'never';
        }
    }

    return $answers;
}

/** The answer that makes `$item` score `$points`. */
function answerScoring(string $key, int $item, int $points): string
{
    $values = ['always', 'almost_always', 'sometimes', 'almost_never', 'never'];

    return $values[in_array($item, ZERO_ON_ALWAYS[$key], true) ? $points : 4 - $points];
}

/**
 * A complete questionnaire whose total is exactly `$total`: items score 4 from the first on, and the next one takes
 * what is left.
 */
function answersTotalling(string $key, int $total): array
{
    $answers = zeroAnswers($key);

    foreach (array_keys($answers) as $item) {
        if (! is_int($item) || $total === 0) {
            continue;
        }

        $points = min(4, $total);
        $answers[$item] = answerScoring($key, $item, $points);
        $total -= $points;
    }

    return $answers;
}

function scoreOf(string $key, array $answers): RiskScore
{
    return resolve(Instruments::class)->scoreRisk($key, $answers, 'dof-2018-10-23');
}

/** @return array<string, GroupScore> */
function groupsByKey(array $groups): array
{
    return collect($groups)->keyBy(fn (GroupScore $group): string => $group->key)->all();
}

it('scores every item on its own scale', function (string $key, string $value): void {
    $answers = nom035Answers($key, $value);
    $expected = 0;

    foreach ($answers as $item => $answer) {
        if (is_int($item)) {
            $expected += itemScore($key, $item, $answer);
        }
    }

    expect(scoreOf($key, $answers)->total)->toBe($expected);
})->with(['nom035.guide-ii', 'nom035.guide-iii'])->with(['always', 'almost_always', 'sometimes', 'almost_never', 'never']);

it('scores one answer to one item: 4 points on "Siempre" for most items, 0 for those scored the other way around', function (string $key, int $item, string $answer, int $score): void {
    expect(resolve(Instruments::class)->itemScore($key, $item, $answer))->toBe($score);
})->with([
    ['nom035.guide-ii', 1, 'always', 4],
    ['nom035.guide-ii', 1, 'almost_never', 1],
    ['nom035.guide-ii', 1, 'never', 0],
    ['nom035.guide-ii', 18, 'always', 0],
    ['nom035.guide-ii', 33, 'never', 4],
    ['nom035.guide-iii', 23, 'always', 0],
    ['nom035.guide-iii', 57, 'almost_always', 1],
    ['nom035.guide-iii', 1, 'sometimes', 2],
]);

it('scores a Guía II answered all "Siempre" with 120: items 18 to 33 score 0 and the other 30 score 4', function (): void {
    expect(scoreOf('nom035.guide-ii', nom035Answers('nom035.guide-ii', 'always'))->total)->toBe(120);
});

it('reaches 0 and the maximum of each guide, 184 and 288', function (string $key, int $maximum): void {
    $lowest = scoreOf($key, zeroAnswers($key));
    $highest = scoreOf($key, answersTotalling($key, $maximum));

    expect($lowest->total)->toBe(0)
        ->and($lowest->level)->toBe(RiskLevel::Null)
        ->and($highest->total)->toBe($maximum)
        ->and($highest->level)->toBe(RiskLevel::VeryHigh);
})->with([['nom035.guide-ii', 184], ['nom035.guide-iii', 288]]);

it('gives 0 to the items of a block whose gate was answered no', function (string $key): void {
    $score = scoreOf($key, nom035Answers($key, 'always', clients: false, supervises: false));
    $expected = 0;

    foreach (nom035Answers($key, 'always', clients: false, supervises: false) as $item => $value) {
        if (is_int($item)) {
            $expected += itemScore($key, $item, $value);
        }
    }

    expect($score->total)->toBe($expected)
        ->and($expected)->toBeLessThan(scoreOf($key, nom035Answers($key, 'always'))->total);
})->with(['nom035.guide-ii', 'nom035.guide-iii']);

it('sums each domain from its items and each category from its domains, as Tablas 3 and 6 group them', function (string $key): void {
    $values = ['always', 'almost_always', 'sometimes', 'almost_never', 'never'];
    $answers = nom035Answers($key);

    foreach (array_keys($answers) as $item) {
        if (is_int($item)) {
            $answers[$item] = $values[$item % 5];
        }
    }

    $score = scoreOf($key, $answers);
    $domains = groupsByKey($score->domains);
    $categories = groupsByKey($score->categories);
    $expectedDomains = [];

    foreach (DOMAIN_ITEMS[$key] as $domain => $items) {
        $expectedDomains[$domain] = array_sum(array_map(fn (int $item): int => itemScore($key, $item, $answers[$item]), $items));
    }

    expect(array_map(fn (GroupScore $group): int => $group->score, $domains))->toBe($expectedDomains);

    foreach ($categories as $category => $group) {
        expect($group->score)->toBe(array_sum(array_map(fn (string $domain): int => $expectedDomains[$domain], CATEGORY_DOMAINS[$category])));
    }

    expect(array_keys($categories))->toBe(array_keys(array_filter(
        CATEGORY_DOMAINS,
        fn (array $domains): bool => array_key_exists($domains[0], DOMAIN_ITEMS[$key]),
    )))
        ->and($score->total)->toBe(array_sum($expectedDomains));
})->with(['nom035.guide-ii', 'nom035.guide-iii']);

it('names the category of each domain, as Tablas 3 and 6 group them, and none for a category', function (string $key): void {
    $score = scoreOf($key, nom035Answers($key));
    $expected = [];

    foreach (CATEGORY_DOMAINS as $category => $domains) {
        foreach ($domains as $domain) {
            if (array_key_exists($domain, DOMAIN_ITEMS[$key])) {
                $expected[$domain] = $category;
            }
        }
    }

    expect(array_map(fn (GroupScore $group): ?string => $group->category, groupsByKey($score->domains)))->toEqualCanonicalizing($expected)
        ->and(array_keys(array_map(fn (GroupScore $group): ?string => $group->category, groupsByKey($score->domains))))->toBe(array_keys(DOMAIN_ITEMS[$key]))
        ->and(array_unique(array_map(fn (GroupScore $group): ?string => $group->category, $score->categories)))->toBe([null]);
})->with(['nom035.guide-ii', 'nom035.guide-iii']);

it('groups every item in exactly one domain', function (string $key, int $count): void {
    $items = array_merge(...array_values(DOMAIN_ITEMS[$key]));
    sort($items);

    expect($items)->toBe(range(1, $count));
})->with([['nom035.guide-ii', 46], ['nom035.guide-iii', 72]]);

it('names each group as the norm does', function (): void {
    $score = scoreOf('nom035.guide-iii', nom035Answers('nom035.guide-iii'));

    expect(array_map(fn (GroupScore $group): string => $group->label, $score->categories))->toBe([
        'Ambiente de trabajo',
        'Factores propios de la actividad',
        'Organización del tiempo de trabajo',
        'Liderazgo y relaciones en el trabajo',
        'Entorno organizacional',
    ])
        ->and(array_map(fn (GroupScore $group): string => $group->label, $score->domains))->toBe([
            'Condiciones en el ambiente de trabajo',
            'Carga de trabajo',
            'Falta de control sobre el trabajo',
            'Jornada de trabajo',
            'Interferencia en la relación trabajo-familia',
            'Liderazgo',
            'Relaciones en el trabajo',
            'Violencia',
            'Reconocimiento del desempeño',
            'Insuficiente sentido de pertenencia e inestabilidad',
        ])
        ->and(array_column($score->dimensions, 'label'))->toContain('Escasa claridad de funciones', 'Carga mental', 'Inestabilidad laboral')
        ->and(count($score->dimensions))->toBe(25);
});

it('keeps "puedo expresarme libremente" (items 33 and 57), scored 0 on "Siempre", inside Violencia', function (string $key, int $item): void {
    $always = zeroAnswers($key);
    $always[$item] = 'never';

    expect(groupsByKey(scoreOf($key, $always)->domains)['violence']->score)->toBe(4);
})->with([['nom035.guide-ii', 33], ['nom035.guide-iii', 57]]);

it('places a total right at each cut-off in the higher level', function (string $key, int $total, RiskLevel $level): void {
    expect(scoreOf($key, answersTotalling($key, $total))->level)->toBe($level);
})->with(function (): array {
    $cases = [];

    foreach (CUTOFFS as $key => $rows) {
        foreach ($rows['final'] as $index => $cutoff) {
            $cases["{$key} {$cutoff} − 1"] = [$key, $cutoff - 1, LEVELS[$index]];
            $cases["{$key} {$cutoff}"] = [$key, $cutoff, LEVELS[$index + 1]];
            $cases["{$key} {$cutoff} + 1"] = [$key, $cutoff + 1, LEVELS[$index + 1]];
        }
    }

    return $cases;
});

it('levels each category and domain by its own ranges, the lower bound inclusive', function (string $key, string $group, int $score, RiskLevel $level): void {
    expect(RiskScorer::levelOf($key, $group, $score))->toBe($level);
})->with(function (): array {
    $cases = [];

    foreach (CUTOFFS as $key => $rows) {
        foreach ($rows as $group => $cutoffs) {
            foreach ($cutoffs as $index => $cutoff) {
                $cases["{$key} {$group} {$cutoff} − 1"] = [$key, $group, $cutoff - 1, LEVELS[$index]];
                $cases["{$key} {$group} {$cutoff}"] = [$key, $group, $cutoff, LEVELS[$index + 1]];

                // Some bounds are one apart (Jornada de trabajo: Bajo from 1, Medio from 2): there, + 1 is the next level.
                if ($cutoff + 1 < ($cutoffs[$index + 1] ?? PHP_INT_MAX)) {
                    $cases["{$key} {$group} {$cutoff} + 1"] = [$key, $group, $cutoff + 1, LEVELS[$index + 1]];
                }
            }
        }
    }

    return $cases;
});

it('levels the groups of a scored questionnaire by their ranges', function (): void {
    // Guía II "violence" (items 33–40): 7 is the lower bound of Bajo.
    $answers = zeroAnswers('nom035.guide-ii');
    $answers[34] = answerScoring('nom035.guide-ii', 34, 4);
    $answers[35] = answerScoring('nom035.guide-ii', 35, 3);
    $score = scoreOf('nom035.guide-ii', $answers);

    expect(groupsByKey($score->domains)['violence']->score)->toBe(7)
        ->and(groupsByKey($score->domains)['violence']->level)->toBe(RiskLevel::Low)
        ->and(groupsByKey($score->categories)['leadership_relations']->level)->toBe(RiskLevel::Null)
        ->and(groupsByKey($score->domains)['workload']->level)->toBe(RiskLevel::Null);
});

it('labels each level as the norm does', function (): void {
    expect(array_map(fn (RiskLevel $level): string => $level->label(), LEVELS))
        ->toBe(['Nulo o despreciable', 'Bajo', 'Medio', 'Alto', 'Muy alto']);
});

it('scores only a valid questionnaire', function (): void {
    scoreOf('nom035.guide-iii', nom035Answers('nom035.guide-ii'));
})->throws(InvalidArgumentException::class);

it('scores only in a version of the norm it has the tables of', function (): void {
    resolve(Instruments::class)->scoreRisk('nom035.guide-ii', nom035Answers('nom035.guide-ii'), 'dof-2030-01-01');
})->throws(InvalidArgumentException::class, 'No scoring tables for [nom035.guide-ii] in version [dof-2030-01-01].');

it('scores only Guías II and III', function (): void {
    scoreOf('nom035.guide-i', guideOneAnswers());
})->throws(InvalidArgumentException::class);
