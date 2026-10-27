#!/usr/bin/env bash
# Stop: when the working tree has changes, run the full quality suite before Claude finishes.
# composer test always runs; when frontend files changed, npm run check and npm run types:check run too.
# A failure blocks the stop once so Claude keeps fixing; a second consecutive failure
# lets it stop and warns the user, to avoid an endless loop.

input=$(cat)
cd "$CLAUDE_PROJECT_DIR" || exit 0

changes=$(git status --porcelain 2>/dev/null)
[ -n "$changes" ] || exit 0

failed=""
report=""

if ! output=$(composer test 2>&1); then
    failed="composer test"
    report=$(echo "$output" | tail -40)
fi

if echo "$changes" | grep -qE '\.(ts|tsx|css)$|(^| )(package(-lock)?\.json|vite\.config\.ts|frontend-architecture\.ts|tsconfig\.json)$'; then
    for check in "npm run check" "npm run types:check"; do
        if ! output=$($check 2>&1); then
            failed="${failed:+$failed, }$check"
            report="$report"$'\n'$(echo "$output" | tail -40)
        fi
    done
fi

[ -z "$failed" ] && exit 0

if [ "$(echo "$input" | jq -r '.stop_hook_active // false')" = "true" ]; then
    echo "{\"systemMessage\": \"$failed sigue fallando; revisa antes de dar el trabajo por terminado.\"}"
    exit 0
fi

echo "$failed failed. The tests and rules define the behavior: fix the code, not the tests or the rules." >&2
echo "$report" >&2
exit 2
