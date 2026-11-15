<?php

declare(strict_types=1);

use Tests\Support\SafeFakerNames;

it('reads from the tests the words they look for to prove they were not leaked', function (): void {
    expect(SafeFakerNames::avoided())->toContain('Rosa', 'Beto', 'Zulema');
});

it('never draws a name, a company or a user name with one of those words', function (): void {
    $faker = Faker\Factory::create('es_ES');
    $faker->addProvider(new SafeFakerNames($faker));
    $draws = [];

    for ($i = 0; $i < 1500; $i++) {
        array_push($draws, $faker->name(), $faker->company(), $faker->userName(), $faker->safeEmail());
    }

    $leaks = array_filter($draws, function (string $draw): bool {
        foreach (SafeFakerNames::avoided() as $word) {
            if (mb_stripos($draw, $word) !== false) {
                return true;
            }
        }

        return false;
    });

    expect($leaks)->toBe([])->and(array_unique($draws))->not->toHaveCount(1);
});
