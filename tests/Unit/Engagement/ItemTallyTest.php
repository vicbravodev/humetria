<?php

declare(strict_types=1);

use App\Modules\Engagement\Support\Audience\Segment;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Results\ItemTally;
use App\Modules\Engagement\Support\Results\ResponseRow;
use App\Modules\Engagement\Support\Thresholds;
use Tests\TestCase;

uses(TestCase::class);

mutates(ItemTally::class);

/**
 * One row per value of `$values` answering `$key` (null = omitted).
 *
 * @param  list<?int>  $values
 * @return list<ResponseRow>
 */
function tallyRows(string $key, array $values): array
{
    return array_map(fn (?int $value): ResponseRow => new ResponseRow(new Segment(null, null, null, null), [$key => $value]), $values);
}

it('counts favorable, neutral and unfavorable among those who answered, leaving out who omitted', function (): void {
    $result = ItemTally::of(ItemBank::find('clarity.goals'), tallyRows('clarity.goals', [5, 5, 4, 3, 2, null]), resolve(Thresholds::class));

    expect($result->key)->toBe('clarity.goals')->and($result->shown)->toBeTrue()->and($result->answered)->toBe(5)
        ->and($result->favorable)->toBe(60)->and($result->neutral)->toBe(20)->and($result->unfavorable)->toBe(20)
        ->and($result->distribution)->toBe([0, 20, 20, 20, 40]);
});

it('hides everything, even how many answered, below the minimum of answers to the item', function (): void {
    $result = ItemTally::of(ItemBank::find('clarity.goals'), tallyRows('clarity.goals', [5, 4, 3, 2, null, null, null, null, null, null, null, null, null, null]), resolve(Thresholds::class));

    expect($result->shown)->toBeFalse()->and($result->answered)->toBeNull()->and($result->favorable)->toBeNull()
        ->and($result->neutral)->toBeNull()->and($result->unfavorable)->toBeNull()->and($result->distribution)->toBeNull();
});

it('shows the item at exactly 5 answers and hides it at 4', function (): void {
    expect(ItemTally::of(ItemBank::find('clarity.goals'), tallyRows('clarity.goals', [4, 4, 4, 4, 4]), resolve(Thresholds::class))->shown)->toBeTrue()
        ->and(ItemTally::of(ItemBank::find('clarity.goals'), tallyRows('clarity.goals', [4, 4, 4, 4]), resolve(Thresholds::class))->shown)->toBeFalse();
});

it('rounds half up: 2 of 3 is 67 and 1 of 3 is 33', function (): void {
    $result = ItemTally::of(ItemBank::find('clarity.goals'), tallyRows('clarity.goals', [5, 4, 1, 4, 5, 3]), resolve(Thresholds::class));

    expect($result->favorable)->toBe(67)->and($result->neutral)->toBe(17)->and($result->unfavorable)->toBe(17);
});

it('ignores the rows that do not carry the key (a person with no manager has no manager answers)', function (): void {
    $rows = [...tallyRows('manager.listening', [4, 4, 4, 4, 4]), ...tallyRows('clarity.goals', [1, 1, 1, 1, 1, 1])];

    $result = ItemTally::of(ItemBank::find('manager.listening'), $rows, resolve(Thresholds::class));

    expect($result->answered)->toBe(5)->and($result->favorable)->toBe(100);
});

it('counts as answered a row that carries the key with a value, whatever the other keys say', function (): void {
    $rows = [new ResponseRow(new Segment(null, null, null, null), ['clarity.goals' => 4, 'clarity.priorities' => null])];
    $rows = [...$rows, ...$rows, ...$rows, ...$rows, ...$rows];

    expect(ItemTally::of(ItemBank::find('clarity.goals'), $rows, resolve(Thresholds::class))->answered)->toBe(5)
        ->and(ItemTally::of(ItemBank::find('clarity.priorities'), $rows, resolve(Thresholds::class))->shown)->toBeFalse();
});

it('does not tally an open question or the 0 to 10 one as an agreement', function (string $key): void {
    expect(fn () => ItemTally::of(ItemBank::find($key), [], resolve(Thresholds::class)))->toThrow(InvalidArgumentException::class);
})->with(['eng.recommend', 'open.best']);
