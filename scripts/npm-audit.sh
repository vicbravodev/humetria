#!/usr/bin/env bash
#
# `npm audit --omit=dev` that fails on any high or critical advisory except the ones allowed below.
# Tests feed it a saved audit through NPM_AUDIT_JSON_FILE (tests/Unit/NpmAuditScriptTest.php).

set -euo pipefail

# Advisories ignored on purpose, each with its reason and a review date. Anything else high or critical fails.
#   GHSA-fc5q-39xc-vjpf: picomatch ReDoS (<= 4.0.7, the latest release: no patched version exists). picomatch is
#   build tooling (Vite, tinyglobby, fdir, plugin-babel) and the globs are fixed by this project, never by a user.
#   Review when a picomatch > 4.0.7 exists, and no later than 2026-12-15.
allowed='["GHSA-fc5q-39xc-vjpf"]'

if [ -n "${NPM_AUDIT_JSON_FILE:-}" ]; then
    audit=$(cat "$NPM_AUDIT_JSON_FILE")
else
    audit=$(npm audit --omit=dev --json || true)
fi

# One line per high or critical advisory: "GHSA-id<TAB>name<TAB>severity". Dependents only repeat a name in "via".
advisories=$(jq -r '[.vulnerabilities[]?.via[]? | objects | select(.severity == "high" or .severity == "critical")
    | [(.url | split("/") | last), .name, .severity] | @tsv] | unique | .[]' <<<"$audit")

failed=0
while IFS=$'\t' read -r id name severity; do
    [ -z "$id" ] && continue
    if jq -e --arg id "$id" 'index($id)' <<<"$allowed" >/dev/null; then
        echo "npm audit: ignored $id ($name, $severity), see scripts/npm-audit.sh"
    else
        echo "npm audit: $id ($name, $severity) is not allowed" >&2
        failed=1
    fi
done <<<"$advisories"

exit "$failed"
