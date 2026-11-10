<?php

declare(strict_types=1);

use App\Modules\Engagement\Support\Audience\ManagerGroups;

mutates(ManagerGroups::class);

/**
 * @param  list<string>  $people
 * @return array<string, string|null> everyone reports to $manager
 */
function reportingTo(?string $manager, array $people): array
{
    return array_fill_keys($people, $manager);
}

it('gives a manager with five direct reports a group of their own', function (): void {
    $people = ['a', 'b', 'c', 'd', 'e'];

    $groups = ManagerGroups::partition(reportingTo('boss', $people) + ['boss' => null], $people, 5);

    expect($groups)->toBe(array_fill_keys($people, 'boss'));
});

it('puts a manager with four direct reports and nobody above in no group', function (): void {
    $people = ['a', 'b', 'c', 'd'];

    expect(ManagerGroups::partition(reportingTo('boss', $people) + ['boss' => null], $people, 5))->toBe(array_fill_keys($people, null));
});

it('puts someone with no manager in no group', function (): void {
    expect(ManagerGroups::partition(['a' => null], ['a'], 5))->toBe(['a' => null]);
});

it('adds a small team to the group of the manager above', function (): void {
    // m has n, a and b; n has x, y and z. Both are under five, so the six are m's.
    $managerOf = ['n' => 'm', 'a' => 'm', 'b' => 'm', 'x' => 'n', 'y' => 'n', 'z' => 'n', 'm' => null];
    $people = ['n', 'a', 'b', 'x', 'y', 'z'];

    expect(ManagerGroups::partition($managerOf, $people, 5))->toBe([
        'n' => 'm', 'a' => 'm', 'b' => 'm', 'x' => 'm', 'y' => 'm', 'z' => 'm',
    ]);
});

it('keeps a big team of its own and hands the small team above to the next manager', function (): void {
    // n leads six; m leads n, p and q (three): m's three go up to b, who has m and r, and so reaches five.
    $managerOf = ['m' => 'b', 'r' => 'b', 'n' => 'm', 'p' => 'm', 'q' => 'm', 'b' => null];
    foreach (['s1', 's2', 's3', 's4', 's5', 's6'] as $person) {
        $managerOf[$person] = 'n';
    }
    $people = ['m', 'r', 'n', 'p', 'q', 's1', 's2', 's3', 's4', 's5', 's6'];

    $groups = ManagerGroups::partition($managerOf, $people, 5);

    expect($groups)->toBe([
        'm' => 'b', 'r' => 'b', 'n' => 'b', 'p' => 'b', 'q' => 'b',
        's1' => 'n', 's2' => 'n', 's3' => 'n', 's4' => 'n', 's5' => 'n', 's6' => 'n',
    ]);
});

it('does not give a group to the manager above when even all together they are too few', function (): void {
    $managerOf = ['m' => 'b', 'a' => 'm', 'b' => null];

    expect(ManagerGroups::partition($managerOf, ['m', 'a'], 5))->toBe(['m' => null, 'a' => null]);
});

it('keeps everybody in one group at most, and every group at the minimum or more, in any tree', function (int $minimum): void {
    mt_srand(20261109);
    $people = array_map(fn (int $i): string => "p{$i}", range(1, 200));
    $managerOf = [];
    foreach ($people as $index => $person) {
        $managerOf[$person] = $index === 0 ? null : $people[mt_rand(0, max(0, $index - 1))];
    }

    $groups = ManagerGroups::partition($managerOf, $people, $minimum);

    expect(array_keys($groups))->toBe($people);
    $sizes = array_count_values(array_filter($groups, fn (?string $manager): bool => $manager !== null));
    expect($sizes)->not->toBe([])
        ->and(min($sizes))->toBeGreaterThanOrEqual($minimum);
})->with([5, 10]);

it('makes fewer groups with a higher minimum', function (): void {
    mt_srand(7);
    $people = array_map(fn (int $i): string => "p{$i}", range(1, 120));
    $managerOf = [];
    foreach ($people as $index => $person) {
        $managerOf[$person] = $index === 0 ? null : $people[mt_rand(0, max(0, $index - 1))];
    }

    $five = array_unique(array_filter(ManagerGroups::partition($managerOf, $people, 5)));
    $ten = array_unique(array_filter(ManagerGroups::partition($managerOf, $people, 10)));

    expect(count($ten))->toBeLessThan(count($five));
});

it('gives the same groups whatever the order of the people', function (): void {
    $managerOf = ['a' => 'z', 'b' => 'z', 'c' => 'y', 'd' => 'y', 'z' => 'top', 'y' => 'top', 'top' => null];
    $people = ['a', 'b', 'c', 'd'];

    $forward = ManagerGroups::partition($managerOf, $people, 3);
    $backward = ManagerGroups::partition($managerOf, array_reverse($people), 3);

    expect($forward)->toBe(['a' => 'top', 'b' => 'top', 'c' => 'top', 'd' => 'top'])
        ->and($backward)->toEqual($forward);
});
