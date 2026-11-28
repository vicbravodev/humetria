#!/usr/bin/env bash
# The weekly mutation run: the business-logic classes (every class a test declares in mutates(), minus controllers,
# form requests and notifications), one module at a time. A single run overflows the OS argument limit: Pest gives
# each mutant's process every test that covers its line, and some lines are covered by thousands. scripts/mutation-classes.php
# --plan gives each module its classes and a --filter with only the test files that mutate them.
#
# Every module runs even if one fails, and the exit code is 1 if any did. Extra arguments go to Pest (--min=95 ...).
#
# With --changed first (composer mutate:changed) it runs only the modules whose app/ code differs from origin/main,
# committed or not: the mutation that closes a feature, before its PR.
#
# Usage: composer mutate:critical [-- --min=95 --ignore-min-score-on-zero-mutations]
set -uo pipefail

status=0

if [ "${1:-}" = "--changed" ]; then
    shift
    plan=$( { git diff --name-only origin/main...HEAD; git diff --name-only HEAD; git ls-files --others --exclude-standard; } | php scripts/mutation-classes.php tests --plan --changed)
else
    plan=$(php scripts/mutation-classes.php tests --plan)
fi

while IFS=$'\t' read -r module classes filter; do
    [ -n "$module" ] || continue
    echo
    echo "=== mutation: $module"
    vendor/bin/pest --mutate --parallel --processes=4 --exclude-testsuite=Browser,Ssr --group=__pest_mutate_only \
        --filter="$filter" --class="$classes" "$@" </dev/null || status=1
done <<< "$plan"

exit $status
