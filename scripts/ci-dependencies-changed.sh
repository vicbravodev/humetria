#!/usr/bin/env bash
#
# Reads the changed paths on stdin, one per line, and prints "true" when the change touches what installs or audits the
# dependencies (tests.yml then runs composer audit and npm audit on the pull request) or "false" otherwise. No path at
# all prints "true": when the change could not be read, the audit runs.

set -euo pipefail

paths=$(grep -v '^$' || true)

if [ -z "$paths" ] || printf '%s\n' "$paths" | grep -qxE 'composer\.(json|lock)|package(-lock)?\.json|scripts/npm-audit\.sh'; then
    echo true
else
    echo false
fi
