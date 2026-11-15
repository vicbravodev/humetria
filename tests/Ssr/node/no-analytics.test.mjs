import assert from 'node:assert/strict';
import { test } from 'node:test';
import { render } from './ssr.mjs';

// Google Analytics is written by Blade in the document (App\Http\Analytics), never by React: Node must neither run nor print it,
// so nothing of it can come out of the render, whatever the measurement id is on the Laravel side.
void test('what Node renders carries no analytics: no gtag, no dataLayer, no Google origin', async () => {
    const { json } = await render();
    const everything = JSON.stringify(json);

    for (const forbidden of [
        'gtag',
        'dataLayer',
        'googletagmanager',
        'google-analytics',
        'generate_lead',
    ]) {
        assert.equal(everything.includes(forbidden), false, forbidden);
    }
});
