#!/usr/bin/env bash
# Stop: when the working tree has changes, run the full quality suite before Claude finishes.
# A failure blocks the stop once so Claude keeps fixing; a second consecutive failure
# lets it stop and warns the user, to avoid an endless loop.

input=$(cat)
cd "$CLAUDE_PROJECT_DIR" || exit 0

[ -n "$(git status --porcelain 2>/dev/null)" ] || exit 0

if output=$(composer test 2>&1); then
    exit 0
fi

if [ "$(echo "$input" | jq -r '.stop_hook_active // false')" = "true" ]; then
    echo '{"systemMessage": "composer test sigue fallando; revisa antes de dar el trabajo por terminado."}'
    exit 0
fi

echo "composer test failed. The tests define the behavior: fix the code, not the tests." >&2
echo "$output" | tail -40 >&2
exit 2
