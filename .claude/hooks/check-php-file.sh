#!/usr/bin/env bash
# PostToolUse (Write|Edit): format the edited PHP file with Pint, then run PHPStan on it.
# Exit 2 sends PHPStan errors back to Claude so it fixes them right away.

file=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty')
cd "$CLAUDE_PROJECT_DIR" || exit 0

case "$file" in
    *.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

vendor/bin/pint --quiet "$file" 2>/dev/null

relative=${file#"$CLAUDE_PROJECT_DIR"/}
case "$relative" in
    app/*|config/*|database/*|routes/*|bootstrap/app.php) ;;
    *) exit 0 ;;
esac

if ! output=$(vendor/bin/phpstan analyse --no-progress --error-format=raw --memory-limit=1G "$file" 2>&1); then
    echo "PHPStan found errors in $relative. Fix the cause; do not suppress them:" >&2
    echo "$output" >&2
    exit 2
fi
