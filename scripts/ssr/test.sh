#!/usr/bin/env bash
# The tests that need a real Node: build the SSR bundle, start it on 13714, wait for /health, run the Pest `Ssr` suite
# (Pest Browser, the app served in-process with SSR on) and the node:test files in tests/Ssr/node, then stop Node and the PHP
# server by their PIDs. The node:test files that drive a browser (no JavaScript, typing before hydration) read the page from
# the PHP server, which PHP's own `-S` runs on 8000 with SSR on and nothing that needs Redis or the app's session table.
# They are outside `composer test`, the coverage and the mutation run: this is their own command, `composer test:ssr`.
#
# Usage: composer test:ssr [-- extra Pest arguments]   (SSR_PREBUILT=true skips the build when the bundle is already there)
set -uo pipefail

port=13714
web_port=8000

for taken in "$port" "$web_port"; do
    if lsof -i ":$taken" >/dev/null 2>&1; then
        echo "Port $taken is taken: stop what is using it (it is not killed from here)." >&2
        exit 1
    fi
done

# CI builds the bundle once, in the build job of tests.yml, and says so with SSR_PREBUILT=true.
if [ "${SSR_PREBUILT:-}" != true ]; then
    npm run build:ssr || exit 1
fi

node bootstrap/ssr/app.js >storage/logs/ssr-test.log 2>&1 &
node_pid=$!
web_pid=
trap 'kill "$node_pid" $web_pid 2>/dev/null; wait "$node_pid" $web_pid 2>/dev/null' EXIT

for _ in $(seq 1 50); do
    curl -fs "http://127.0.0.1:$port/health" >/dev/null && break
    sleep 0.2
done

if ! curl -fs "http://127.0.0.1:$port/health" >/dev/null; then
    echo "Node did not answer /health:" >&2
    cat storage/logs/ssr-test.log >&2
    exit 1
fi

INERTIA_SSR_ENABLED=true INERTIA_SSR_URL="http://127.0.0.1:$port" INERTIA_SSR_ENSURE_BUNDLE=true \
    SESSION_DRIVER=file CACHE_STORE=file DB_HOST=127.0.0.1 DB_PORT=5433 \
    php -S "127.0.0.1:$web_port" -t public \
    >storage/logs/ssr-test-web.log 2>&1 &
web_pid=$!

for _ in $(seq 1 50); do
    curl -fs -o /dev/null "http://127.0.0.1:$web_port/" && break
    sleep 0.2
done

status=0

BASE_URL="http://127.0.0.1:$web_port" SSR_URL="http://127.0.0.1:$port" node --test "tests/Ssr/node/*.test.mjs" || status=1
php artisan test --testsuite=Ssr "$@" || status=1

exit "$status"
