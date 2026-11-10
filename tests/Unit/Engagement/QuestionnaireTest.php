<?php

declare(strict_types=1);

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\Questionnaire;

mutates(Questionnaire::class);

/**
 * @param  list<BankItem>  $items
 * @return list<string>
 */
function engagementKeys(array $items): array
{
    return array_map(fn (BankItem $item): string => $item->key, $items);
}

it('asks 40 closed and 4 open questions the first time, without the loop question', function (): void {
    $refs = Questionnaire::climate(false);
    $presented = Questionnaire::presentedTo($refs, true);

    expect($refs)->toHaveCount(44)
        ->and($refs)->not->toContain('loop.action@1')
        ->and($refs[0])->toBe('eng.pride@1')
        ->and(array_filter($presented, fn (BankItem $item): bool => $item->scale !== ItemScale::Open))->toHaveCount(40)
        ->and(array_filter($presented, fn (BankItem $item): bool => $item->scale === ItemScale::Open))->toHaveCount(4);
});

it('adds the loop question from the second wave on, in the place the bank has it', function (): void {
    $refs = Questionnaire::climate(true);

    expect($refs)->toHaveCount(45)
        ->and($refs)->toContain('loop.action@1')
        ->and(array_search('loop.action@1', $refs, true))->toBe(array_search('manager.change@1', $refs, true) + 1)
        ->and(engagementKeys(Questionnaire::presentedTo($refs, true)))->toBe(engagementKeys(ItemBank::all()));
});

it('takes the manager block away from someone without a manager, in the second wave too', function (): void {
    $presented = Questionnaire::presentedTo(Questionnaire::climate(true), false);

    expect(array_filter($presented, fn (BankItem $item): bool => str_starts_with($item->key, 'manager.')))->toBe([])
        ->and(array_filter($presented, fn (BankItem $item): bool => $item->block === ItemBlock::Manager))->toBe([])
        ->and(array_filter($presented, fn (BankItem $item): bool => $item->scale !== ItemScale::Open))->toHaveCount(33)
        ->and(array_filter($presented, fn (BankItem $item): bool => $item->scale === ItemScale::Open))->toHaveCount(2);
});

it('keeps the manager block for someone who has a manager', function (): void {
    $presented = Questionnaire::presentedTo(Questionnaire::climate(true), true);

    expect(array_filter($presented, fn (BankItem $item): bool => $item->block === ItemBlock::Manager))->toHaveCount(10);
});

it('refuses a ref whose wording is no longer the bank one, a key the bank does not have and a malformed ref', function (string $ref): void {
    expect(fn () => Questionnaire::presentedTo([$ref], true))->toThrow(InvalidArgumentException::class);
})->with(['old wording' => 'eng.pride@2', 'unknown key' => 'nope.nope@1', 'no wording' => 'eng.pride', 'empty wording' => 'eng.pride@', 'text wording' => 'eng.pride@one']);
