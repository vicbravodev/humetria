import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { landingPage } from './ssr.mjs';

const keys = JSON.parse(
    readFileSync(new URL('../fixtures/page-keys.json', import.meta.url)),
);

// The healthcheck of the ssr image renders this file: when Laravel sends other keys, it must age with them.
void test('the fixture the healthcheck renders has the keys Laravel sends', () => {
    const page = landingPage();

    assert.deepEqual(
        Object.keys(page).sort((a, b) => a.localeCompare(b)),
        keys.page,
    );
    assert.deepEqual(
        Object.keys(page.props).sort((a, b) => a.localeCompare(b)),
        [...keys.props, ...keys.pageProps.landing].sort((a, b) =>
            a.localeCompare(b),
        ),
    );
});
