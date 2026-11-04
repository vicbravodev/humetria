#!/usr/bin/env bash
# Stop: when the working tree has changes, run the FAST checks before Claude finishes: Pint, PHPStan and only the tests
# the change affects (composer check:fast, Tia). When frontend files changed, npm run check, types:check and test:js
# run too. The full suite (coverage, browser) is composer ci:check, run before the pull request, and CI repeats it.
# It runs in the checkout the session works in (a worktree, not $CLAUDE_PROJECT_DIR), whose tests use their own database.
# A failure blocks the stop once so Claude keeps fixing; a second consecutive failure
# lets it stop and warns the user, to avoid an endless loop.

input=$(cat)
cd "$(echo "$input" | jq -r '.cwd // empty')" 2>/dev/null || cd "$CLAUDE_PROJECT_DIR" || exit 0
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 0

changes=$(git status --porcelain 2>/dev/null)
[ -n "$changes" ] || exit 0

failed=""
report=""

if ! output=$(composer check:fast 2>&1); then
    failed="composer check:fast"
    report=$(echo "$output" | tail -40)
fi

if echo "$changes" | grep -qE '\.(ts|tsx|css)$|(^| )(package(-lock)?\.json|vite\.config\.ts|frontend-architecture\.ts|tsconfig\.json)$'; then
    for check in "npm run check" "npm run types:check" "npm run test:js"; do
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
