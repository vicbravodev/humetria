#!/usr/bin/env bash
# Writes a worktree's .env from the main checkout's, neutral for tests: no PostHog project token and consent denied, so
# CSP, consent and attribution tests do not depend on the owner's real keys. Every other setting is copied as is.
#
# Usage: scripts/worktree-env.sh <main .env> <worktree .env>
set -euo pipefail

awk '
    /^[[:space:]]*POSTHOG_PROJECT_TOKEN[[:space:]]*=/ { print "POSTHOG_PROJECT_TOKEN="; token = 1; next }
    /^[[:space:]]*POSTHOG_CONSENT_DEFAULT[[:space:]]*=/ { print "POSTHOG_CONSENT_DEFAULT=denied"; consent = 1; next }
    { print }
    END {
        if (!token) print "POSTHOG_PROJECT_TOKEN="
        if (!consent) print "POSTHOG_CONSENT_DEFAULT=denied"
    }
' "$1" > "$2"
