#!/usr/bin/env bash
# The weekly mutation run: the business-logic classes (every class a test declares in mutates(), minus controllers,
# form requests and notifications), one module at a time. A single run overflows the OS argument limit: Pest gives
# each mutant's process every test that covers its line, and some lines are covered by thousands. scripts/mutation-classes.php
# --plan gives each module its classes and a --filter with only the test files that mutate them.
#
# Every module runs even if one fails, and the exit code is 1 if any did. Extra arguments go to Pest (--min=95 ...).
#
# Usage: composer mutate:critical [-- --min=95 --ignore-min-score-on-zero-mutations]
set -uo pipefail

status=0

while IFS=$'\t' read -r module classes filter; do
    echo
    echo "=== mutation: $module"
    vendor/bin/pest --mutate --parallel --processes=4 --exclude-testsuite=Browser --group=__pest_mutate_only \
        --filter="$filter" --class="$classes" "$@" </dev/null || status=1
done < <(php scripts/mutation-classes.php tests --plan)

exit $status
