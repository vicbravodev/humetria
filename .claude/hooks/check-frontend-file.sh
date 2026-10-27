#!/usr/bin/env bash
# PostToolUse (Write|Edit): format the edited TS/TSX file, then lint it with the project's rules
# (frontend architecture, size limits, oxlint). Exit 2 sends the errors back to Claude so it fixes them right away.

file=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty')
cd "$CLAUDE_PROJECT_DIR" || exit 0

case "$file" in
    *.ts|*.tsx) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

node_modules/.bin/vp fmt "$file" >/dev/null 2>&1

relative=${file#"$CLAUDE_PROJECT_DIR"/}
if ! output=$(node_modules/.bin/vp lint "$file" 2>&1); then
    echo "vp lint found problems in $relative. Fix the cause (split the component, move logic to hooks/lib); do not loosen the rules:" >&2
    echo "$output" >&2
    exit 2
fi
