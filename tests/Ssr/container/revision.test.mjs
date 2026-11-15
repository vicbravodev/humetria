import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from '../node/ssr.mjs';

// Run by scripts/ssr/container-test.sh from a sibling container, against the ssr image: the commit it was built from
// (EXPECTED_REVISION, the GIT_SHA build argument) reaches the head Node renders.
void test('the image tells the commit it was built from in the build-revision meta', async () => {
    const { json } = await render();
    const tag = json.head.find((html) =>
        html.includes('name="build-revision"'),
    );

    assert.ok(tag, 'a build-revision tag in the head');
    assert.ok(tag.includes(`content="${process.env.EXPECTED_REVISION}"`), tag);
});
