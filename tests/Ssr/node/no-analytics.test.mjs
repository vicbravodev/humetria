import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

// PostHog's configuration is written by Blade in the document (App\Http\Analytics), never by React: Node must neither run nor print it,
// so nothing of it can come out of the render, whatever the project token is on the Laravel side.
void test('what Node renders carries no analytics: no config block, no PostHog origin, no token', async () => {
    const { json } = await render();
    const everything = JSON.stringify(json);

    for (const forbidden of [
        'analytics-config',
        'posthog',
        'phc_',
        'generate_lead',
    ]) {
        assert.equal(everything.includes(forbidden), false, forbidden);
    }
});
