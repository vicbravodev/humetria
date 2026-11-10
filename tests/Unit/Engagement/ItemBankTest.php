<?php

declare(strict_types=1);

use App\Modules\Engagement\Data\BankItem;
use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\ItemBlock;
use App\Modules\Engagement\Enums\ItemScale;
use App\Modules\Engagement\Support\Bank\ItemBank;

mutates(ItemBank::class);

/**
 * The questions of the document of the bank, in its order: the document is the source of truth, so the test reads it.
 *
 * @return list<array{key: string, text: string, anchor: bool, candidate: bool, open: bool}>
 */
function bankDocumentItems(): array
{
    $lines = file(__DIR__.'/../../../docs/superpowers/specs/2026-11-09-engagement-item-bank.md', FILE_IGNORE_NEW_LINES);
    $marks = [' *(candidato)*', ' *(abierta, opcional)*', ' *(a partir de la segunda ola)*'];
    $items = [];

    foreach ($lines === false ? [] : $lines as $line) {
        if (preg_match('/^\| `([a-z_]+\.[a-z_]+)`( Ⓟ)? \| (.+?) \|(?: [^|]+ \|)?$/u', $line, $row) === 1) {
            $items[] = [
                'key' => $row[1],
                'text' => str_replace($marks, '', $row[3]),
                'anchor' => $row[2] !== '',
                'candidate' => str_contains($row[3], '*(candidato)*'),
                'open' => str_contains($row[3], '*(abierta, opcional)*'),
            ];
        } elseif (preg_match('/^- `(manager\.(?:keep|change))`: (.+)$/u', $line, $row) === 1) {
            $items[] = ['key' => $row[1], 'text' => $row[2], 'anchor' => false, 'candidate' => false, 'open' => true];
        }
    }

    return $items;
}

it('reproduces the document of the bank question by question, in its order', function (): void {
    $document = bankDocumentItems();
    $bank = ItemBank::all();

    expect($document)->toHaveCount(45)
        ->and(array_map(fn (BankItem $item): string => $item->key, $bank))->toBe(array_column($document, 'key'))
        ->and(array_map(fn (BankItem $item): string => $item->text, $bank))->toBe(array_column($document, 'text'))
        ->and(array_map(fn (BankItem $item): bool => $item->anchor, $bank))->toBe(array_column($document, 'anchor'))
        ->and(array_map(fn (BankItem $item): bool => $item->candidate, $bank))->toBe(array_column($document, 'candidate'));
});

it('has 41 closed and 4 open questions, and every open one is optional', function (): void {
    expect(ItemBank::closed())->toHaveCount(41)
        ->and(ItemBank::open())->toHaveCount(4)
        ->and(array_map(fn (BankItem $item): string => $item->key, ItemBank::open()))
        ->toBe(['manager.keep', 'manager.change', 'open.best', 'open.improve'])
        ->and(array_column(array_filter(bankDocumentItems(), fn (array $item): bool => $item['open']), 'key'))
        ->toBe(['manager.keep', 'manager.change', 'open.best', 'open.improve']);
});

it('puts every question in the block of its section of the document', function (): void {
    $blocks = [];
    foreach (ItemBank::all() as $item) {
        $blocks[$item->block->name][] = $item->key;
    }

    expect($blocks['Outcome'])->toBe(['eng.pride', 'eng.motivation', 'eng.connection', 'eng.recommend', 'eng.stay'])
        ->and($blocks['Company'])->toHaveCount(27)
        ->and($blocks['Manager'])->toHaveCount(10)
        ->and($blocks['Closing'])->toBe(['loop.action', 'open.best', 'open.improve']);
});

it('marks 11 anchors, one for each driver and the one of the manager', function (): void {
    $anchors = array_values(array_filter(ItemBank::all(), fn (BankItem $item): bool => $item->anchor));

    expect($anchors)->toHaveCount(11)
        ->and(ItemBank::anchor(Driver::Reward)->key)->toBe('reward.pay')
        ->and(ItemBank::anchor(Driver::Clarity)->key)->toBe('clarity.contribution')
        ->and(ItemBank::find('manager.guidance')->anchor)->toBeTrue();

    foreach (Driver::cases() as $driver) {
        expect(ItemBank::anchor($driver)->driver)->toBe($driver);
    }
});

it('keeps two candidates and nothing else on trial', function (): void {
    $candidates = array_map(fn (BankItem $item): string => $item->key, array_filter(ItemBank::all(), fn (BankItem $item): bool => $item->candidate));

    expect(array_values($candidates))->toBe(['communication.change', 'reward.benefits']);
});

it('gives the recommendation question its own 0 to 10 scale and every other closed question 1 to 5', function (): void {
    $recommend = array_values(array_filter(ItemBank::all(), fn (BankItem $item): bool => $item->scale === ItemScale::Recommend));

    expect($recommend)->toHaveCount(1)
        ->and($recommend[0]->key)->toBe('eng.recommend')
        ->and(ItemScale::Recommend->range())->toBe([0, 10])
        ->and(ItemScale::Agreement->range())->toBe([1, 5])
        ->and(ItemScale::Open->range())->toBeNull();

    foreach (ItemBank::open() as $item) {
        expect($item->scale)->toBe(ItemScale::Open);
    }
});

it('groups the company questions under ten drivers named by the prefix of their key', function (): void {
    $sizes = [];
    foreach (Driver::cases() as $driver) {
        $items = ItemBank::ofDriver($driver);
        $sizes[$driver->value] = count($items);
        foreach ($items as $item) {
            expect($item->block)->toBe(ItemBlock::Company)
                ->and($item->key)->toStartWith("{$driver->value}.");
        }
    }

    expect($sizes)->toBe(['clarity' => 3, 'leadership' => 3, 'communication' => 4, 'recognition' => 2, 'growth' => 2, 'autonomy' => 2, 'resources' => 3, 'team' => 2, 'voice' => 3, 'reward' => 3]);
});

it('titles the drivers as the headings of the document do', function (): void {
    $lines = file(__DIR__.'/../../../docs/superpowers/specs/2026-11-09-engagement-item-bank.md', FILE_IGNORE_NEW_LINES);
    $titles = [];
    foreach ($lines === false ? [] : $lines as $line) {
        if (preg_match('/^### \d+\. (.+)$/u', $line, $heading) === 1) {
            $titles[] = $heading[1];
        }
    }

    expect($titles)->toHaveCount(10)
        ->and(array_map(fn (Driver $driver): string => $driver->label(), Driver::cases()))->toBe($titles);
});

it('reports a driver of two questions question by question', function (): void {
    expect(Driver::Team->descriptive())->toBeTrue()
        ->and(Driver::Recognition->descriptive())->toBeTrue()
        ->and(Driver::Growth->descriptive())->toBeTrue()
        ->and(Driver::Autonomy->descriptive())->toBeTrue()
        ->and(Driver::Voice->descriptive())->toBeFalse()
        ->and(Driver::Communication->descriptive())->toBeFalse();
});

it('finds a question by its key and refuses an unknown one', function (): void {
    expect(ItemBank::find('eng.recommend')->scale)->toBe(ItemScale::Recommend)
        ->and(ItemBank::find('team.respect')->driver)->toBe(Driver::Team)
        ->and(fn () => ItemBank::find('nope'))->toThrow(InvalidArgumentException::class);
});

it('names a wording with the key and its number so a rewrite never edits the text in place', function (): void {
    expect(ItemBank::find('eng.pride')->ref())->toBe('eng.pride@1')
        ->and((new BankItem('eng.pride', 'x', ItemScale::Agreement, ItemBlock::Outcome, null, wording: 2))->ref())->toBe('eng.pride@2')
        ->and(ItemBank::VERSION)->toBe('v1');
});

it('names the questions of the engagement index, the recommendation, the stay and the loop', function (): void {
    expect(ItemBank::INDEX)->toBe(['eng.pride', 'eng.motivation', 'eng.connection'])
        ->and(ItemBank::RECOMMEND)->toBe('eng.recommend')
        ->and(ItemBank::STAY)->toBe('eng.stay')
        ->and(ItemBank::LOOP)->toBe('loop.action');
});

it('has the five labels of the agreement scale and the fixed texts of the survey', function (): void {
    expect(ItemBank::AGREEMENT_LABELS)->toBe([
        1 => 'Totalmente en desacuerdo',
        2 => 'En desacuerdo',
        3 => 'Ni de acuerdo ni en desacuerdo',
        4 => 'De acuerdo',
        5 => 'Totalmente de acuerdo',
    ])
        ->and(ItemBank::OPEN_NOTICE)->toBe('No escriba nombres ni datos que permitan identificarle a usted o a otra persona. Para reportar acoso o violencia use el canal de quejas.')
        ->and(ItemBank::MANAGER_INTRO)->toBe('Las siguientes preguntas son sobre su jefe directo: :name. Sus respuestas se juntan con las de sus compañeros y su jefe solo ve el resultado del grupo. El sistema no guarda quién contestó qué.');
});
