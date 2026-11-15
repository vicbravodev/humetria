#!/usr/bin/env bash
#
# Reads the changed paths on stdin, one per line, and prints "true" when the pipeline must run or "false" when every
# path is documentation nothing reads: anything under docs/ and the markdown files at the root of the repository.
# tests.yml skips its jobs on "false" and deploy.yml does not redeploy.
#
# A document that a test or the app reads is code. tests/Unit/CiCodeChangedTest.php finds those and fails until they are
# listed here.
#
# No path at all prints "true": when the change could not be read, the pipeline runs.

set -euo pipefail

code=false
seen=false

while IFS= read -r path || [ -n "$path" ]; do
    [ -z "$path" ] && continue
    seen=true

    case "$path" in
        # The norms: the instrument tests read their reference texts, and Humi reads their criteria (config/humi.php).
        docs/normas/*) code=true ;;
        # The item bank of climate and pulse, read by tests/Unit/Engagement/ItemBankTest.php.
        docs/superpowers/specs/2026-11-09-engagement-item-bank.md) code=true ;;
        # The operations manuals: tests/Unit/OperationsDocsTest.php checks them against the code.
        docs/operations/*) code=true ;;
        # The share image: tests/Feature/PageMetaTest.php reads its source.
        docs/design-system/og/*) code=true ;;
        docs/*) ;;
        # Anything else inside a directory is code, a README next to the code included.
        */*) code=true ;;
        # The rules of the project: tests/Unit/OperationsDocsTest.php checks them against the code.
        CLAUDE.md) code=true ;;
        *.md) ;;
        *) code=true ;;
    esac
done

[ "$seen" = true ] || code=true

echo "$code"
