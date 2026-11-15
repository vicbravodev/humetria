#!/usr/bin/env bash
#
# The verdict of the required check (tests.yml, job ci). Reads on stdin the `needs` context of that job, as JSON, and
# fails when a job it waits for did not pass.
#
# A job passes when it succeeded. A skipped job passes only when the job changes succeeded and answered "false": the
# change was documentation nothing reads (scripts/ci-code-changed.sh). The job changes itself must always succeed.

set -euo pipefail

failed=$(jq -r '
    (if .changes.result == "success" then .changes.outputs.code else null end) as $code
    | to_entries[]
    | select(
        .value.result != "success"
        and ((.key != "changes" and .value.result == "skipped" and $code == "false") | not)
    )
    | "\(.key): \(.value.result)"
')

if [ -n "$failed" ]; then
    echo "Failed jobs:"
    echo "$failed"
    exit 1
fi
