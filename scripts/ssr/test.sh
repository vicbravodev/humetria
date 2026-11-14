#!/usr/bin/env bash
# The tests that need a real Node: build the SSR bundle, start it on 13714, wait for /health, run the Pest `Ssr` suite
# (Pest Browser, the app served in-process with SSR on) and the node:test files in tests/Ssr/node, then stop Node by its PID.
# They are outside `composer test`, the coverage and the mutation run: this is their own command, `composer test:ssr`.
#
# Usage: composer test:ssr [-- extra Pest arguments]
set -uo pipefail

port=13714

if lsof -i ":$port" >/dev/null 2>&1; then
    echo "Port $port is taken: stop what is using it (it is not killed from here)." >&2
    exit 1
fi

npm run build:ssr || exit 1

node bootstrap/ssr/app.js >storage/logs/ssr-test.log 2>&1 &
node_pid=$!
trap 'kill "$node_pid" 2>/dev/null; wait "$node_pid" 2>/dev/null' EXIT

for _ in $(seq 1 50); do
    curl -fs "http://127.0.0.1:$port/health" >/dev/null && break
    sleep 0.2
done

if ! curl -fs "http://127.0.0.1:$port/health" >/dev/null; then
    echo "Node did not answer /health:" >&2
    cat storage/logs/ssr-test.log >&2
    exit 1
fi

status=0

SSR_URL="http://127.0.0.1:$port" node --test "tests/Ssr/node/*.test.mjs" || status=1
php artisan test --testsuite=Ssr "$@" || status=1

exit "$status"
