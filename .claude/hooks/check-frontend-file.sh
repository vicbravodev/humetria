#!/usr/bin/env bash
# PostToolUse (Write|Edit): format the edited TS/TSX file, then lint it with the project's rules
# (frontend architecture, size limits, oxlint). Exit 2 sends the errors back to Claude so it fixes them right away.

file=$(jq -r '.tool_response.filePath // .tool_input.file_path // empty')
# The checkout that owns the file: a worktree is not $CLAUDE_PROJECT_DIR, and its code must be checked with its own config.
root=$(git -C "$(dirname "$file")" rev-parse --show-toplevel 2>/dev/null || echo "$CLAUDE_PROJECT_DIR")
cd "$root" || exit 0

case "$file" in
    *.ts|*.tsx) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

node_modules/.bin/vp fmt "$file" >/dev/null 2>&1

relative=${file#"$root"/}
if ! output=$(node_modules/.bin/vp lint "$file" 2>&1); then
    echo "vp lint found problems in $relative. Fix the cause (split the component, move logic to hooks/lib); do not loosen the rules:" >&2
    echo "$output" >&2
    exit 2
fi
