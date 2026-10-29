<?php

declare(strict_types=1);

/*
 * Adds up the "Mutations:" lines on stdin (one per CI shard, as `pest --mutate` prints them) and fails below the
 * minimum score given as the argument. The score is Pest's: tested and timed out mutations over all of them.
 *
 * Usage: printf '%s\n' "$shard1" "$shard2" | php scripts/mutation-score.php 85
 */

$minimum = (float) ($argv[1] ?? 85);
$counts = ['untested' => 0, 'uncovered' => 0, 'pending' => 0, 'timeout' => 0, 'tested' => 0];

preg_match_all('/^\s*Mutations:(.*)$/m', stream_get_contents(STDIN) ?: '', $lines);
preg_match_all('/(\d+) (untested|uncovered|pending|timeout|tested)\b/', implode(',', $lines[1]), $parts, PREG_SET_ORDER);

foreach ($parts as [, $count, $label]) {
    $counts[$label] += (int) $count;
}

$total = array_sum($counts);

if ($total === 0) {
    echo "Nothing was mutated.\n";

    exit(0);
}

$score = ($counts['tested'] + $counts['timeout']) / $total * 100;

printf("Score: %.2f%% over %d mutations (minimum %s%%).\n", $score, $total, $minimum);

exit($score >= $minimum ? 0 : 1);
