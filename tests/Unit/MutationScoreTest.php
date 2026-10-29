<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * scripts/mutation-score.php adds up the "Mutations:" lines the CI shards print and holds the total to the minimum
 * score, as a single `pest --mutate --min` would: a shard that holds the mutants no test reaches can't fail alone.
 */

/** @return array{output: string, passed: bool} */
function mutationScoreFor(string $input, string $min = '85'): array
{
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/mutation-score.php', $min]);
    $process->setInput($input);
    $process->run();

    return ['output' => trim($process->getOutput()), 'passed' => $process->isSuccessful()];
}

it('scores the shards together as Pest scores one run: tested and timed out over every mutation', function (): void {
    $result = mutationScoreFor(implode("\n", [
        '  Mutations: 6 untested, 47 uncovered, 134 tested',
        '  Mutations: 147 tested',
        '  Mutations: 2 pending, 3 timeout, 61 tested',
    ]));

    // (134 + 147 + 3 + 61) / 400
    expect($result)->toBe(['output' => 'Score: 86.25% over 400 mutations (minimum 85%).', 'passed' => true]);
});

it('fails when the total is below the minimum, even if some shard passes', function (): void {
    $result = mutationScoreFor("Mutations: 40 uncovered, 60 tested\nMutations: 100 tested\n", '81');

    expect($result)->toBe(['output' => 'Score: 80.00% over 200 mutations (minimum 81%).', 'passed' => false]);
});

it('reads only the "Mutations:" lines of whatever it gets', function (): void {
    $result = mutationScoreFor("Mutating application files...\n  Mutations: 1 untested, 3 tested\n  Score: 75.00%\n", '75');

    expect($result)->toBe(['output' => 'Score: 75.00% over 4 mutations (minimum 75%).', 'passed' => true]);
});

it('passes when no shard mutated anything', function (): void {
    expect(mutationScoreFor("\n\n"))->toBe(['output' => 'Nothing was mutated.', 'passed' => true]);
});
