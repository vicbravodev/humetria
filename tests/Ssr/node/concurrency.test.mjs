import assert from 'node:assert/strict';
import { test } from 'node:test';
import { landingPage, render } from './ssr.mjs';

// Node is one long-lived process: the page of one request must never leak into the answer of another.
void test('50 renders at once each answer with their own page and no other', async () => {
    const marks = Array.from({ length: 50 }, (_, index) => `mark-${index}-x`);
    const answers = await Promise.all(
        marks.map((mark) => {
            const page = landingPage();
            page.props.errors = { mark };
            page.url = `/?${mark}`;

            return render(page);
        }),
    );

    answers.forEach(({ status, json }, index) => {
        assert.equal(status, 200);
        const found = marks.filter((mark) => json.body.includes(mark));
        assert.deepEqual(found, [marks[index]]);
    });
});
