#!/usr/bin/env bash
# The ssr image, for real (needs Docker): build it, run it with the restrictions of compose.production.yaml on an internal
# network, wait until Docker calls it healthy and check what the design (section 7.2) says about it: it runs as node, writes
# nowhere, has no .env, vendor or app, reaches nothing outside its network, a sibling on that network reaches it by its alias,
# and it renders the landing (tests/Ssr/node/render.test.mjs) stamped with the commit (tests/Ssr/container).
# It creates a container and a network of its own, named after the image, and removes them. It pushes nothing.
#
# Usage: composer test:ssr:container     (SSR_TEST_IMAGE=name to choose the local tag, default humetria-ssr-test)
set -uo pipefail

image=${SSR_TEST_IMAGE:-humetria-ssr-test}
revision=$(git rev-parse HEAD)
network="$image-net"
container="$image-run"
status=0

cleanup() {
    docker rm -f "$container" >/dev/null 2>&1
    docker network rm "$network" >/dev/null 2>&1
}
trap cleanup EXIT

check() {
    local label=$1
    shift
    if "$@" >/dev/null 2>&1; then
        echo "ok   $label"
    else
        echo "FAIL $label" >&2
        status=1
    fi
}

docker build --target ssr --build-arg "GIT_SHA=$revision" -t "$image" . || exit 1
docker network create --internal "$network" >/dev/null || exit 1

# The restrictions of compose.production.yaml; the health interval is shortened so the test does not wait half a minute.
docker run -d --name "$container" --network "$network" --network-alias humetria-ssr \
    --init --read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges:true \
    --memory 384m --cpus 1.0 --pids-limit 100 \
    -e NODE_ENV=production -e NODE_OPTIONS=--max-old-space-size=256 \
    --health-interval 2s --health-start-period 0s --health-retries 3 \
    "$image" >/dev/null || exit 1

healthy=
for _ in $(seq 1 60); do
    healthy=$(docker inspect -f '{{.State.Health.Status}}' "$container")
    [ "$healthy" = healthy ] && break
    sleep 1
done

check "the container is healthy (a real render, not just the port)" test "$healthy" = healthy
check "it runs as node, not root" test "$(docker exec "$container" node -p 'process.getuid()')" != 0
check "its filesystem is read-only" bash -c "! docker exec $container sh -c 'touch /srv/ssr/x'"
check "it has no .env, vendor or app" docker exec "$container" node -e \
    "process.exit(['.env','vendor','app','artisan'].some((p) => require('fs').existsSync('/srv/ssr/' + p)) ? 1 : 0)"
check "it reaches nothing outside its network" docker exec "$container" node -e \
    "fetch('https://example.com',{signal:AbortSignal.timeout(4000)}).then(()=>process.exit(1),()=>process.exit(0))"

# A sibling on the same network, with the repository mounted: the Node tests reach the container by its alias.
sibling=(docker run --rm --network "$network" --entrypoint node -v "$PWD:/repo:ro" -w /repo -e SSR_URL=http://humetria-ssr:13714 "$image")
check "a sibling reaches /health by the alias" "${sibling[@]}" -e "fetch('http://humetria-ssr:13714/health').then((r)=>process.exit(r.ok?0:1))"
check "render.test.mjs passes against the container" "${sibling[@]}" --test tests/Ssr/node/render.test.mjs
check "the head carries the commit" docker run --rm --network "$network" --entrypoint node -v "$PWD:/repo:ro" -w /repo \
    -e SSR_URL=http://humetria-ssr:13714 -e "EXPECTED_REVISION=$revision" "$image" --test tests/Ssr/container/revision.test.mjs

docker stats --no-stream --format 'memory in use: {{.MemUsage}} (limit from compose: 384m)' "$container"
docker image inspect "$image" --format 'image size: {{.Size}} bytes'

exit "$status"
