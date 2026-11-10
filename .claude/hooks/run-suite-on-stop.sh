#!/usr/bin/env bash
# Stop: when the working tree has changes, run only the STATIC checks on what changed: Pint (--test) and PHPStan on the
# changed PHP files, and npm run check + types:check when frontend files changed. Tests are NOT run here: the first
# run of Tia replays the whole suite (35+ minutes), which made every turn crawl. Tests are run by whoever builds the
# task (the file or filter of the task), the full suite is composer ci:check before the pull request, and CI repeats it.
# A failure blocks the stop once so Claude keeps fixing; a second consecutive failure
# lets it stop and warns the user, to avoid an endless loop.

input=$(cat)
cd "$(echo "$input" | jq -r '.cwd // empty')" 2>/dev/null || cd "$CLAUDE_PROJECT_DIR" || exit 0
cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 0

changes=$(git status --porcelain 2>/dev/null)
[ -n "$changes" ] || exit 0

failed=""
report=""

if echo "$changes" | grep -qE '\.php$'; then
    if ! output=$(vendor/bin/pint --dirty --test 2>&1); then
        failed="pint"
        report=$(echo "$output" | tail -40)
    fi
    files=$(echo "$changes" | awk '{print $NF}' | grep -E '^(app|config|database|routes|bootstrap/app)\b.*\.php$' | while read -r f; do [ -f "$f" ] && echo "$f"; done)
    if [ -n "$files" ]; then
        # shellcheck disable=SC2086
        if ! output=$(vendor/bin/phpstan analyse --no-progress --error-format=raw --memory-limit=2G $files 2>&1); then
            failed="${failed:+$failed, }phpstan"
            report="$report"$'\n'$(echo "$output" | tail -40)
        fi
    fi
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
