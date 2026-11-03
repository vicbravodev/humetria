<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Instruments;
use App\Modules\Instruments\Data\ItemDefinition;
use App\Modules\Instruments\Exceptions\InvalidAnswers;
use App\Modules\Instruments\Support\Seating\SeatingSurvey;

mutates(SeatingSurvey::class);

it('is an anonymous, plain-language survey of the confidential kind of criterio C-19, answered with «usted»', function (): void {
    $definition = resolve(Instruments::class)->definition('seating.survey');

    expect($definition->key)->toBe('seating.survey')->and($definition->identified)->toBeFalse()
        ->and($definition->version)->toBe('dof-2025-07-17')
        ->and(array_column($definition->options, 'value'))->toBe(['yes', 'no', 'not_applicable'])
        ->and(array_column($definition->options, 'label'))->toBe(['Sí', 'No', 'No aplica'])
        ->and($definition->sections)->toHaveCount(1)
        ->and($definition->sections[0]->gate?->key)->toBe('stands')
        ->and($definition->sections[0]->gate->question)->toContain('de pie');
});

it('has the items with their SS codes, each tied to the requirement it detects, and says they are not DOF text', function (): void {
    $definition = resolve(Instruments::class)->definition('seating.survey');
    $items = $definition->sections[0]->items;

    expect(array_map(fn (ItemDefinition $item): string => SeatingSurvey::code($item->number), $items))->toBe(['SS-01', 'SS-02', 'SS-03', 'SS-04', 'SS-05', 'SS-06', 'SS-07', 'SS-08'])
        ->and(SeatingSurvey::requirements())->toHaveCount(8)->and(array_keys(SeatingSurvey::requirements()))->toBe(['SS-01', 'SS-02', 'SS-03', 'SS-04', 'SS-05', 'SS-06', 'SS-07', 'SS-08'])
        ->and(SeatingSurvey::NOTICE)->toContain('no son texto del DOF');
    foreach ($items as $item) {
        expect($item->text)->not->toContain('Disposiciones')->not->toContain('DOF')->and(mb_substr($item->text, 0, 1))->toBe('¿');
    }
});

it('asks about seat availability, sitting without consequence, the pauses, the seat condition, time on foot and discomfort', function (): void {
    $text = implode(' ', array_map(fn (ItemDefinition $item): string => $item->text, resolve(Instruments::class)->definition('seating.survey')->sections[0]->items));

    expect($text)->toContain('asiento')->toContain('sentarse')->toContain('pausas')->toContain('buen estado')->toContain('tres horas')->toContain('molestias');
});

it('accepts a complete survey, and the one of a person who does not stand, with nothing else', function (): void {
    $instruments = resolve(Instruments::class);

    $instruments->validate('seating.survey', surveyAnswers());
    $instruments->validate('seating.survey', surveyAnswers('not_applicable'));
    $instruments->validate('seating.survey', ['stands' => 'no']);

    expect(true)->toBeTrue();
});

it('refuses an incomplete survey, an invented answer, an answer after «no» and a gate left open', function (array $answers): void {
    resolve(Instruments::class)->validate('seating.survey', $answers);
})->with([
    'an item left out' => fn (): array => array_slice(surveyAnswers(), 0, 5, true),
    'no gate' => fn (): array => array_diff_key(surveyAnswers(), ['stands' => 1]),
    'an invented option' => fn (): array => [...surveyAnswers(), '3' => 'maybe'],
    'an invented item' => fn (): array => [...surveyAnswers(), '99' => 'yes'],
    'items after «no»' => fn (): array => ['stands' => 'no', '1' => 'yes'],
    'a gate that is not yes or no' => fn (): array => [...surveyAnswers(), 'stands' => 'sometimes'],
])->throws(InvalidAnswers::class);

it('is not scored by risk level: it adds nothing to the individual assessment', function (): void {
    resolve(Instruments::class)->scoreRisk('seating.survey', surveyAnswers(), 'dof-2025-07-17');
})->throws(InvalidArgumentException::class);
